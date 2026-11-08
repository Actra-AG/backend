<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\libs\auth\SharedLoginSession;
use actra\backend\settings\SharedLoginSettings;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\RecordingCookieWriter;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\SharedLogin;
use actra\yuf\auth\SharedLoginSite;
use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\exception\NotFoundException;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The shared login (`ActraBackendSettings::$sharedLogin`, `docs/login.md`): a site and its login host with the same
 * directory, each with its own browser cookies and session. The site uses the backend login page as its login page.
 */
final class SharedLoginTest extends TestCase
{
    private const string SITE_HOST = 'site.example.net';
    private const string LOGIN_HOST = 'login.example.com';
    private const string HAND_OVER_PATH = '/backend/loginShared.html';
    private const string RETURN_PATH = '/backend/users.html';
    private TestUsers $testUsers;
    /** `''` while `setUp()` did not get that far (no test database: the test is skipped) */
    private string $directory = '';
    private RecordingCookieWriter $siteCookies;
    private RecordingCookieWriter $loginHostCookies;
    private RecordingMailer $mailer;
    private BackendPageRenderer $site;
    private BackendPageRenderer $loginHost;
    private Session $siteSession;
    private Session $loginHostSession;
    private RecordingResponseSender $lastResponseSender;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->directory = sys_get_temp_dir() . '/backend-shared-login-' . bin2hex(string: random_bytes(length: 8));
        $this->siteCookies = new RecordingCookieWriter();
        $this->loginHostCookies = new RecordingCookieWriter();
        $this->mailer = new RecordingMailer();
        $this->site = $this->createRenderer(
            cookieWriter: $this->siteCookies,
            allowedHosts: [],
            site: new SharedLoginSite(
                loginPath: '/backend/login.html',
                handOverPath: SharedLoginTest::HAND_OVER_PATH,
                loginHostUrl: 'https://' . SharedLoginTest::LOGIN_HOST . SharedLoginTest::HAND_OVER_PATH,
            ),
        );
        $this->loginHost = $this->createRenderer(
            cookieWriter: $this->loginHostCookies,
            allowedHosts: [SharedLoginTest::SITE_HOST],
            site: null,
        );
        $this->siteSession = new Session(storage: new ArraySessionStorage());
        $this->loginHostSession = new Session(storage: new ArraySessionStorage());
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

    public function testSettingsAreEitherTheLoginHostOrASite(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SharedLoginSettings(
            sharedLogin: new SharedLogin(directory: $this->directory, loginLifetimeInSeconds: 3600),
            allowedHosts: [],
            site: null,
        );
    }

    /**
     * After the login on the site, invisible redirects over the login host log the same user in there and return to
     * the requested page of the site.
     */
    public function testLoginOnTheSiteIsPushedToTheLoginHost(): void
    {
        $userId = $this->testUsers->create(email: $email = $this->createEmail());

        $pushUrl = $this->logInOnTheSite(email: $email);

        $this->assertStringStartsWith('https://' . SharedLoginTest::LOGIN_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginPush=', $pushUrl);
        $confirmUrl = $this->requestLoginHost(url: $pushUrl);
        $this->assertStringStartsWith('https://' . SharedLoginTest::SITE_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginPush=', $confirmUrl);
        $lastUrl = $this->requestSite(url: $confirmUrl);
        $this->assertStringStartsWith('https://' . SharedLoginTest::LOGIN_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginPush=', $lastUrl);
        $this->assertSame(
            'https://' . SharedLoginTest::SITE_HOST . SharedLoginTest::RETURN_PATH,
            $this->requestLoginHost(url: $lastUrl),
        );
        $this->assertTrue(new AuthSession(session: $this->loginHostSession)->isLoggedIn());
        $this->assertSame($userId, $this->loginHost->actraBackend->getRepositories()->sessions()->selectById(
            id: new AuthSession(session: $this->loginHostSession)->getAuthSessionId(),
        )?->dbAuthUser->id);
        $this->assertNotNull(SharedLoginSession::find(session: $this->siteSession));
        $this->assertSame(
            SharedLoginSession::find(session: $this->siteSession),
            SharedLoginSession::find(session: $this->loginHostSession),
        );
    }

    /**
     * A visitor without login on the login host: the check leads back to the login page of the site, which then shows
     * its form (marked with `loginChecked=1`: no second check, no loop).
     */
    public function testCheckWithoutLoginOnTheLoginHostShowsTheLoginPageOfTheSite(): void
    {
        $checkUrl = $this->requestSite(fileTitle: 'login', query: [
            'returnTo' => SharedLoginTest::RETURN_PATH,
        ]);
        $this->assertStringStartsWith('https://' . SharedLoginTest::LOGIN_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginCheck=', $checkUrl);

        $answerUrl = $this->requestLoginHost(url: $checkUrl);
        $this->assertStringStartsWith('https://' . SharedLoginTest::SITE_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginAnswer=', $answerUrl);
        $loginUri = $this->requestSite(url: $answerUrl);
        $this->assertSame(
            'https://' . SharedLoginTest::SITE_HOST . '/backend/login.html?returnTo=%2Fbackend%2Fusers.html&loginChecked=1',
            $loginUri,
        );

        $loginPage = $this->requestSite(fileTitle: 'login', query: [
            'returnTo' => SharedLoginTest::RETURN_PATH,
            'loginChecked' => '1',
        ]);
        $this->assertStringContainsString('action="?LoginForm"', $loginPage);
        $this->assertFalse(new AuthSession(session: $this->siteSession)->isLoggedIn());
        // No memory of the check: the next visit of the login page without the marker checks again
        $this->assertStringStartsWith(
            'https://' . SharedLoginTest::LOGIN_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginCheck=',
            $this->requestSite(fileTitle: 'login'),
        );
    }

    /**
     * A visitor logged in on the login host gets the login on the site without a visible page.
     */
    public function testCheckWithLoginOnTheLoginHostLogsInOnTheSite(): void
    {
        $userId = $this->testUsers->create(email: $this->createEmail());
        $this->loginHost->logIn(session: $this->loginHostSession, userId: $userId);
        $sharedLoginId = SharedLogin::createSharedLoginId();
        SharedLoginSession::store(session: $this->loginHostSession, sharedLoginId: $sharedLoginId);

        $checkUrl = $this->requestSite(fileTitle: 'login', query: [
            'returnTo' => SharedLoginTest::RETURN_PATH,
        ]);
        $answerUrl = $this->requestLoginHost(url: $checkUrl);

        $this->assertSame(
            'https://' . SharedLoginTest::SITE_HOST . SharedLoginTest::RETURN_PATH,
            $this->requestSite(url: $answerUrl),
        );
        $this->assertTrue(new AuthSession(session: $this->siteSession)->isLoggedIn());
        $this->assertSame($sharedLoginId, SharedLoginSession::find(session: $this->siteSession));
    }

    /**
     * The logout on one host ends the login on the other host at its next request.
     */
    public function testLogoutOnTheSiteEndsTheLoginOnTheLoginHost(): void
    {
        $this->testUsers->create(email: $email = $this->createEmail());
        $lastUrl = $this->requestSite(url: $this->requestLoginHost(url: $this->logInOnTheSite(email: $email)));
        $this->requestLoginHost(url: $lastUrl);
        $this->assertTrue(new AuthSession(session: $this->loginHostSession)->isLoggedIn());

        $this->requestSite(fileTitle: 'logout');
        $this->assertFalse(new AuthSession(session: $this->siteSession)->isLoggedIn());

        // The login host logs out at its next request: the page that needs a login refuses it
        try {
            $this->requestLoginHost(fileTitle: 'users');
            SharedLoginTest::fail('The login host is still logged in.');
        } catch (UnauthorizedAccessRightException) {
        }
        $this->assertFalse(new AuthSession(session: $this->loginHostSession)->isLoggedIn());
    }

    /**
     * A login of before the shared login (no ID in the session) gets an ID and stays logged in.
     */
    public function testLoginWithoutSharedLoginIdGetsOne(): void
    {
        $userId = $this->testUsers->create(email: $this->createEmail());
        $this->loginHost->logIn(session: $this->loginHostSession, userId: $userId);

        $this->requestLoginHost(fileTitle: 'users');

        $this->assertTrue(new AuthSession(session: $this->loginHostSession)->isLoggedIn());
        $this->assertNotNull(SharedLoginSession::find(session: $this->loginHostSession));
    }

    /**
     * The hand-over page shows nothing: without a shared login it does not exist (`ViewRenderTest` cannot render it).
     */
    public function testHandOverPageIsNotFoundWithoutSharedLogin(): void
    {
        $this->expectException(NotFoundException::class);

        new BackendPageRenderer(dbSettings: TestDatabase::settings())->render(
            languageCode: 'de',
            fileTitle: 'loginShared',
            pathVars: [],
            session: new Session(storage: new ArraySessionStorage()),
        );
    }

    /**
     * The page that continues after the form is shown once, only with the ID stored in the session: a second request
     * or another ID is a normal step of the hand-over page (here refused: the login page of the site).
     */
    public function testContinuationIsShownOnceWithItsId(): void
    {
        $this->testUsers->create(email: $email = $this->createEmail());
        $location = $this->postCodeOnTheSite(email: $email);
        $this->continueOnTheSite(url: $location);
        $loginPage = 'https://' . SharedLoginTest::SITE_HOST . '/backend/login.html?loginChecked=1';

        $this->assertSame($loginPage, $this->requestSite(url: $location));
        // Another browser session, another ID
        $this->siteSession = new Session(storage: new ArraySessionStorage());
        $this->testUsers->create(email: $otherEmail = $this->createEmail());
        $this->postCodeOnTheSite(email: $otherEmail);
        $this->assertSame($loginPage, $this->requestSite(query: ['loginContinue' => str_repeat(string: 'c', times: 32)]));
    }

    /**
     * A user with rights of the project only (no page of the backend navigation) logs in on the site: the page that
     * continues after the form is shown all the same.
     */
    public function testUserWithoutBackendPagesContinuesToTheLoginHost(): void
    {
        $db = TestDatabase::connect();
        $userId = $this->testUsers->create(email: $email = $this->createEmail(), withRights: false);
        $rightName = 'project_' . bin2hex(string: random_bytes(length: 4));
        $db->execute(sql: 'INSERT INTO auth_rights SET name=?, title=?', parameters: [$rightName, 'Project']);
        $db->execute(sql: 'INSERT INTO auth_groups SET title=?', parameters: [$rightName]);
        $groupId = $db->getLastInsertId();
        $db->execute(
            sql: 'INSERT INTO auth_group_right SET group_id=?, right_name=?',
            parameters: [$groupId, $rightName],
        );
        $db->execute(
            sql: 'INSERT INTO auth_group_user SET user_id=?, group_id=?',
            parameters: [$userId, $groupId],
        );

        $this->assertStringStartsWith(
            'https://' . SharedLoginTest::LOGIN_HOST . SharedLoginTest::HAND_OVER_PATH . '?loginPush=',
            $this->logInOnTheSite(email: $email),
        );
    }

    public function testBadStepOnTheSiteLeadsToItsLoginPage(): void
    {
        $this->assertSame(
            'https://' . SharedLoginTest::SITE_HOST . '/backend/login.html?loginChecked=1',
            $this->requestSite(url: 'https://' . SharedLoginTest::SITE_HOST . SharedLoginTest::HAND_OVER_PATH
                . '?loginAnswer=' . str_repeat(string: 'a', times: 64)),
        );
        $this->assertFalse(new AuthSession(session: $this->siteSession)->isLoggedIn());
    }

    /**
     * @param list<string> $allowedHosts
     */
    private function createRenderer(
        RecordingCookieWriter $cookieWriter,
        array $allowedHosts,
        ?SharedLoginSite $site,
    ): BackendPageRenderer {
        return new BackendPageRenderer(
            dbSettings: TestDatabase::settings(),
            mailer: $this->mailer,
            sharedLogin: new SharedLoginSettings(
                sharedLogin: new SharedLogin(
                    directory: $this->directory,
                    loginLifetimeInSeconds: 3600,
                    cookieWriter: $cookieWriter,
                ),
                allowedHosts: $allowedHosts,
                site: $site,
            ),
        );
    }

    private function createEmail(): string
    {
        return 'shared-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    /**
     * The login page of the site (checked already), then the code: the target after the login.
     */
    private function logInOnTheSite(string $email): string
    {
        return $this->continueOnTheSite(url: $this->postCodeOnTheSite(email: $email));
    }

    /**
     * The login page of the site (checked already), then the code: the redirect of the response to the code form.
     */
    private function postCodeOnTheSite(string $email): string
    {
        $query = ['returnTo' => SharedLoginTest::RETURN_PATH, 'loginChecked' => '1'];
        $this->requestSite(fileTitle: 'login', query: $query);
        $this->requestSite(
            fileTitle: 'login',
            query: $query,
            post: ['email' => $email, 'returnTo' => SharedLoginTest::RETURN_PATH],
            formName: 'LoginForm',
        );
        $code = $this->lastResponseSender->sendMailsAndGetLoginCode(mailer: $this->mailer);

        $location = $this->requestSite(
            fileTitle: 'loginToken',
            post: ['token' => $code, 'returnTo' => SharedLoginTest::RETURN_PATH],
            formName: 'LoginTokenForm',
        );
        // CSP `form-action 'self'`: the response to the form never redirects to another host
        $this->assertMatchesRegularExpression(
            '#^https://' . preg_quote(str: SharedLoginTest::SITE_HOST) . SharedLoginTest::HAND_OVER_PATH
                . '\?loginContinue=[0-9a-f]{32}$#D',
            $location,
        );

        return $location;
    }

    /**
     * The page that continues after the form: the URL of its refresh (also its link).
     */
    private function continueOnTheSite(string $url): string
    {
        $html = $this->requestSite(url: $url);
        $this->assertSame(1, preg_match(
            pattern: '#<meta http-equiv="refresh" content="0;url=([^"]+)"/>#',
            subject: $html,
            matches: $matches,
        ));
        $continueUrl = html_entity_decode(string: $matches[1]);
        $this->assertStringContainsString('<a href="' . $matches[1] . '">Weiter</a>', $html);

        return $continueUrl;
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $post
     */
    private function requestSite(
        ?string $url = null,
        string $fileTitle = 'loginShared',
        array $query = [],
        array $post = [],
        ?string $formName = null,
    ): string {
        return $this->request(
            renderer: $this->site,
            host: SharedLoginTest::SITE_HOST,
            session: $this->siteSession,
            cookies: $this->siteCookies,
            url: $url,
            fileTitle: $fileTitle,
            query: $query,
            post: $post,
            formName: $formName,
        );
    }

    /**
     * @param array<string, string> $query
     */
    private function requestLoginHost(
        ?string $url = null,
        string $fileTitle = 'loginShared',
        array $query = [],
    ): string {
        return $this->request(
            renderer: $this->loginHost,
            host: SharedLoginTest::LOGIN_HOST,
            session: $this->loginHostSession,
            cookies: $this->loginHostCookies,
            url: $url,
            fileTitle: $fileTitle,
            query: $query,
            post: [],
            formName: null,
        );
    }

    /**
     * A request of a page (with the query of `$url` if given); its HTML, or the target of its redirect.
     *
     * @param array<string, string> $query
     * @param array<string, string> $post Posted with the CSRF token of the session
     */
    private function request(
        BackendPageRenderer $renderer,
        string $host,
        Session $session,
        RecordingCookieWriter $cookies,
        ?string $url,
        string $fileTitle,
        array $query,
        array $post,
        ?string $formName,
    ): string {
        if ($url !== null) {
            $this->assertSame($host, parse_url(url: $url, component: PHP_URL_HOST));
            parse_str(string: (string) parse_url(url: $url, component: PHP_URL_QUERY), result: $urlQuery);
            foreach ($urlQuery as $name => $value) {
                if (is_string(value: $name) && is_string(value: $value)) {
                    $query[$name] = $value;
                }
            }
        }
        $this->lastResponseSender = new RecordingResponseSender();
        try {
            return $renderer->render(
                languageCode: 'de',
                fileTitle: $fileTitle,
                pathVars: [],
                session: $session,
                postParameters: $post === [] ? [] : [
                    CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session),
                ] + $post,
                formName: $formName,
                queryParameters: $query,
                responseSender: $this->lastResponseSender,
                host: $host,
                cookies: $cookies->cookies,
            );
        } catch (ResponseSentException) {
            return $this->lastResponseSender->sentResponse?->getHeader(key: 'Location') ?? '';
        }
    }
}
