<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\settings\LoginHandOverSettings;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\RecordingCookieWriter;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\LoginHandOver;
use actra\yuf\clock\FixedClock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpRequest;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The login on another host of the same server: the site `site.example.net` starts a login request, the backend login
 * pages of the login host `login.example.com` carry it through the steps and hand the login over, the hand-over page
 * of the site (`loginTicket`) logs the user in. Both hosts share the user database.
 */
final class LoginHandOverTest extends TestCase
{
    private const string SITE_HOST = 'site.example.net';
    private const string LOGIN_HOST = 'login.example.com';
    private const string REQUESTED_PATH = '/backend/users.html?reset';
    private const string LOGIN_LINK = '<a href="/backend/login.html">Anmelden</a>';
    private TestUsers $testUsers;
    /** `''` while `setUp()` did not get that far (no test database: the test is skipped) */
    private string $directory = '';
    private RecordingCookieWriter $siteCookies;
    private RecordingCookieWriter $loginHostCookies;
    private RecordingMailer $mailer;
    private BackendPageRenderer $site;
    private BackendPageRenderer $loginHost;
    private Session $loginHostSession;
    private Session $siteSession;
    private RecordingResponseSender $lastResponseSender;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->directory = sys_get_temp_dir() . '/backend-login-hand-over-' . bin2hex(string: random_bytes(length: 8));
        $this->siteCookies = new RecordingCookieWriter();
        $this->loginHostCookies = new RecordingCookieWriter();
        $this->mailer = new RecordingMailer(clock: new SystemClock());
        $this->site = $this->createRenderer(cookieWriter: $this->siteCookies, allowedHosts: []);
        $this->loginHost = $this->createRenderer(
            cookieWriter: $this->loginHostCookies,
            allowedHosts: [LoginHandOverTest::SITE_HOST],
        );
        $this->loginHostSession = new Session(storage: new ArraySessionStorage());
        $this->siteSession = new Session(storage: new ArraySessionStorage());
        $this->lastResponseSender = new RecordingResponseSender();
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->directory === '' || !is_dir(filename: $this->directory)) {
            return;
        }
        $fileNames = scandir(directory: $this->directory);
        foreach ($fileNames === false ? [] : $fileNames as $fileName) {
            if (is_file(filename: $this->directory . '/' . $fileName)) {
                unlink(filename: $this->directory . '/' . $fileName);
            }
        }
        rmdir(directory: $this->directory);
    }

    /**
     * @param list<string> $allowedHosts
     */
    private function createRenderer(
        RecordingCookieWriter $cookieWriter,
        array $allowedHosts,
        ?FixedClock $clock = null,
    ): BackendPageRenderer {
        return new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            loginHandOver: new LoginHandOverSettings(
                loginHandOver: $clock === null
                    ? new LoginHandOver(directory: $this->directory, clock: new SystemClock(), cookieWriter: $cookieWriter)
                    : new LoginHandOver(directory: $this->directory, clock: $clock, cookieWriter: $cookieWriter),
                allowedHosts: $allowedHosts,
            ),
            mailer: $this->mailer,
        );
    }

    private function createUser(): string
    {
        $email = 'hand-over-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
        $this->testUsers->create(email: $email);

        return $email;
    }

    /**
     * The site redirects a visitor without login to the login host (`createRemoteLoginTarget()`); the ID of the login
     * request.
     */
    private function startLogin(): string
    {
        $loginUri = $this->site->actraBackend->createRemoteLoginTarget(
            url: 'https://' . LoginHandOverTest::LOGIN_HOST . '/backend/login.html',
        )->createLoginUri(httpRequest: new HttpRequest(
            host: LoginHandOverTest::SITE_HOST,
            uri: LoginHandOverTest::REQUESTED_PATH,
            cookies: $this->siteCookies->cookies,
        ));
        $this->assertSame(
            1,
            preg_match(
                pattern: '#^https://login\.example\.com/backend/login\.html\?loginRequest=([0-9a-f]{64})$#D',
                subject: $loginUri,
                matches: $matches,
            ),
        );

        return $matches[1];
    }

    /**
     * A request of a page; its HTML, or the target of its redirect (`Location`).
     *
     * @param array<string, string> $queryParameters
     * @param array<string, string> $postParameters Posted with the CSRF token of the session
     */
    private function request(
        BackendPageRenderer $renderer,
        string $host,
        Session $session,
        RecordingCookieWriter $cookies,
        string $fileTitle,
        array $queryParameters = [],
        array $postParameters = [],
        ?string $formName = null,
    ): string {
        $this->lastResponseSender = new RecordingResponseSender();
        try {
            return $renderer->render(
                languageCode: 'de',
                fileTitle: $fileTitle,
                pathVars: [],
                session: $session,
                postParameters: $postParameters === [] ? [] : [
                    CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session),
                ] + $postParameters,
                formName: $formName,
                queryParameters: $queryParameters,
                responseSender: $this->lastResponseSender,
                host: $host,
                cookies: $cookies->cookies,
            );
        } catch (ResponseSentException) {
            return $this->lastResponseSender->sentResponse?->getHeader(key: 'Location') ?? '';
        }
    }

    /**
     * @param array<string, string> $queryParameters
     * @param array<string, string> $postParameters
     */
    private function requestLoginHost(
        string $fileTitle,
        array $queryParameters = [],
        array $postParameters = [],
        ?string $formName = null,
    ): string {
        return $this->request(
            renderer: $this->loginHost,
            host: LoginHandOverTest::LOGIN_HOST,
            session: $this->loginHostSession,
            cookies: $this->loginHostCookies,
            fileTitle: $fileTitle,
            queryParameters: $queryParameters,
            postParameters: $postParameters,
            formName: $formName,
        );
    }

    /**
     * The login page of the login host, then the code: the target after the login.
     */
    private function logInOnTheLoginHost(string $email, string $loginRequestId): string
    {
        $loginPage = $this->requestLoginHost(fileTitle: 'login', queryParameters: ['loginRequest' => $loginRequestId]);
        $hiddenField = '<input type="hidden" name="loginRequest" value="' . $loginRequestId . '">';
        $carried = $loginRequestId !== '' && str_contains(haystack: $loginPage, needle: $hiddenField);
        $codePage = $this->requestLoginHost(
            fileTitle: 'login',
            postParameters: ['email' => $email, 'loginRequest' => $carried ? $loginRequestId : ''],
            formName: 'LoginForm',
        );
        $code = $this->lastResponseSender->sendMailsAndGetLoginCode(mailer: $this->mailer);
        parse_str(string: (string) parse_url(url: $codePage, component: PHP_URL_QUERY), result: $codeQuery);
        $carriedId = $codeQuery['loginRequest'] ?? '';
        $this->assertSame($carried ? $loginRequestId : '', is_string(value: $carriedId) ? $carriedId : '');
        $codePageHtml = $this->requestLoginHost(
            fileTitle: 'loginToken',
            queryParameters: $carried ? ['loginRequest' => $loginRequestId] : [],
        );
        $this->assertSame($carried, $carried && str_contains(haystack: $codePageHtml, needle: $hiddenField));

        $location = $this->requestLoginHost(
            fileTitle: 'loginToken',
            postParameters: ['token' => $code, 'loginRequest' => $carried ? $loginRequestId : ''],
            formName: 'LoginTokenForm',
        );
        // CSP `form-action 'self'`: the response to the form never redirects to another host
        $this->assertSame(LoginHandOverTest::LOGIN_HOST, parse_url(url: $location, component: PHP_URL_HOST));
        if (!str_contains(haystack: $location, needle: '/backend/loginShared.html?loginContinue=')) {
            return $location;
        }
        // The page of the login host that continues to the site (a new navigation)
        parse_str(string: (string) parse_url(url: $location, component: PHP_URL_QUERY), result: $query);
        $continueId = $query['loginContinue'] ?? '';
        $html = $this->requestLoginHost(
            fileTitle: 'loginShared',
            queryParameters: ['loginContinue' => is_string(value: $continueId) ? $continueId : ''],
        );
        $this->assertSame(1, preg_match(
            pattern: '#<meta http-equiv="refresh" content="0;url=([^"]+)"/>#',
            subject: $html,
            matches: $matches,
        ));

        return html_entity_decode(string: $matches[1]);
    }

    /**
     * The hand-over page of the site with the ticket of the URL; its HTML, or the target of its redirect.
     */
    private function redeem(string $handOverUrl, ?BackendPageRenderer $site = null): string
    {
        parse_str(string: (string) parse_url(url: $handOverUrl, component: PHP_URL_QUERY), result: $query);
        $ticket = $query['loginTicket'] ?? '';

        return $this->request(
            renderer: $site ?? $this->site,
            host: LoginHandOverTest::SITE_HOST,
            session: $this->siteSession,
            cookies: $this->siteCookies,
            fileTitle: 'loginTicket',
            queryParameters: ['loginTicket' => is_string(value: $ticket) ? $ticket : ''],
        );
    }

    /**
     * The hand-over URL of a complete login with a login request of the site.
     */
    private function handOverUrl(string $email): string
    {
        $handOverUrl = $this->logInOnTheLoginHost(email: $email, loginRequestId: $this->startLogin());
        $this->assertMatchesRegularExpression(
            '#^https://site\.example\.net/backend/loginTicket\.html\?loginTicket=[0-9a-f]{64}$#D',
            $handOverUrl,
        );

        return $handOverUrl;
    }

    public function testLoginIsHandedOverToTheSite(): void
    {
        $email = $this->createUser();
        $handOverUrl = $this->handOverUrl(email: $email);

        $this->assertSame('https://' . LoginHandOverTest::SITE_HOST . LoginHandOverTest::REQUESTED_PATH, $this->redeem(
            handOverUrl: $handOverUrl,
        ));
        $this->assertTrue(new AuthSession(session: $this->siteSession)->isLoggedIn());
        $this->assertSame(
            [AuthResultEnum::SUCCESSFUL_OTP_LOGIN->value, AuthResultEnum::SUCCESSFUL_SSO_LOGIN->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
    }

    /**
     * Logged in on the login host already: the next login request of a site is handed over at once.
     */
    public function testLoggedInUserIsHandedOverAtOnce(): void
    {
        $this->handOverUrl(email: $this->createUser());

        $this->assertMatchesRegularExpression(
            '#^https://site\.example\.net/backend/loginTicket\.html\?loginTicket=[0-9a-f]{64}$#D',
            $this->requestLoginHost(fileTitle: 'login', queryParameters: ['loginRequest' => $this->startLogin()]),
        );
    }

    public function testWithoutLoginRequestTheLoginStaysLocal(): void
    {
        $this->assertSame(
            'https://' . LoginHandOverTest::LOGIN_HOST . '/backend/users.html?reset',
            $this->logInOnTheLoginHost(email: $this->createUser(), loginRequestId: ''),
        );
    }

    public function testWithAnInvalidLoginRequestTheLoginStaysLocal(): void
    {
        $this->assertSame(
            'https://' . LoginHandOverTest::LOGIN_HOST . '/backend/users.html?reset',
            $this->logInOnTheLoginHost(email: $this->createUser(), loginRequestId: str_repeat(string: 'a', times: 64)),
        );
    }

    /**
     * A login request of a host the login host does not serve is ignored.
     */
    public function testLoginRequestOfAHostThatIsNotAllowedStaysLocal(): void
    {
        $this->loginHost = $this->createRenderer(cookieWriter: $this->loginHostCookies, allowedHosts: ['example.net']);

        $this->assertSame(
            'https://' . LoginHandOverTest::LOGIN_HOST . '/backend/users.html?reset',
            $this->logInOnTheLoginHost(email: $this->createUser(), loginRequestId: $this->startLogin()),
        );
    }

    public function testUsedTicketShowsTheLoginLink(): void
    {
        $handOverUrl = $this->handOverUrl(email: $this->createUser());
        $this->redeem(handOverUrl: $handOverUrl);
        new AuthSession(session: $this->siteSession)->logOut();

        $html = $this->redeem(handOverUrl: $handOverUrl);

        $this->assertStringContainsString(LoginHandOverTest::LOGIN_LINK, $html);
        $this->assertStringContainsString('Anmeldung nicht abgeschlossen', $html);
        $this->assertFalse(new AuthSession(session: $this->siteSession)->isLoggedIn());
    }

    public function testInvalidTicketShowsTheLoginLink(): void
    {
        $html = $this->redeem(handOverUrl: '/backend/loginTicket.html?loginTicket=' . str_repeat(string: 'b', times: 64));

        $this->assertStringContainsString(LoginHandOverTest::LOGIN_LINK, $html);
        $this->assertFalse(new AuthSession(session: $this->siteSession)->isLoggedIn());
    }

    public function testExpiredTicketShowsTheLoginLink(): void
    {
        $handOverUrl = $this->handOverUrl(email: $this->createUser());
        $later = $this->createRenderer(
            cookieWriter: $this->siteCookies,
            allowedHosts: [],
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '+2 minutes')),
        );

        $this->assertStringContainsString(
            LoginHandOverTest::LOGIN_LINK,
            $this->redeem(handOverUrl: $handOverUrl, site: $later),
        );
        $this->assertFalse(new AuthSession(session: $this->siteSession)->isLoggedIn());
    }
}
