<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\libs\auth\BackendLogin;
use actra\backend\libs\auth\LoginCodeRequestResultEnum;
use actra\backend\libs\auth\LoginCodeResultEnum;
use actra\backend\libs\auth\PasswordLoginResultEnum;
use actra\backend\libs\form\LoginForm;
use actra\backend\libs\form\LoginTokenForm;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\RecordingLogger;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\LoginRateLimit;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\Route;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;
use actra\yuf\security\RateLimitFailModeEnum;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * `BackendLogin` on a route of the project (`/`, not the backend route), as a login page of the frontend uses it.
 */
final class BackendLoginTest extends TestCase
{
    private const string PASSWORD = 'correct horse battery staple';
    private const string IP_ADDRESS = '192.0.2.10';
    private TestUsers $testUsers;
    private RecordingResponseSender $responseSender;
    private RecordingMailer $mailer;
    private Session $session;
    /** `''` while `setUp()` did not get that far (no test database: the test is skipped) */
    private string $rateLimitDirectory = '';

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->responseSender = new RecordingResponseSender();
        $this->mailer = new RecordingMailer();
        $this->session = new Session(storage: new ArraySessionStorage());
        $this->rateLimitDirectory = sys_get_temp_dir() . '/backend-rate-limit-'
            . bin2hex(string: random_bytes(length: 8));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->rateLimitDirectory === '' || !is_dir(filename: $this->rateLimitDirectory)) {
            return;
        }
        $files = glob(pattern: $this->rateLimitDirectory . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->rateLimitDirectory);
    }

    private static function email(string $name): string
    {
        return $name . '-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    private function createActraBackend(?LoginRateLimit $loginRateLimit = null): ActraBackend
    {
        return ActraBackendTestInstance::create(
            dbSettings: TestDatabase::settings(),
            loginRateLimit: $loginRateLimit,
            mailer: $this->mailer,
        );
    }

    /**
     * A login rate limit of one attempt per email address in a fresh directory.
     */
    private function createLoginRateLimit(): LoginRateLimit
    {
        return new LoginRateLimit(
            rateLimiter: new RateLimiter(
                directory: $this->rateLimitDirectory,
                secret: str_repeat(string: 's', times: 32),
                purgeProbabilityPercent: 0,
            ),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: new RecordingLogger(),
            clientLimits: [new RateLimit(maxHits: 100, windowSeconds: 900)],
            accountLimits: [new RateLimit(maxHits: 1, windowSeconds: 900)],
        );
    }

    /**
     * The login of a request of a frontend page (`/<fileTitle>.html`) in the session of the test.
     *
     * @param array<string, string> $queryParameters
     * @param array<string, string> $postParameters Posted with the CSRF token of the session
     */
    private function createLogin(
        ActraBackend $actraBackend,
        string $fileTitle = 'login',
        array $queryParameters = [],
        array $postParameters = [],
        ?Session $session = null,
    ): BackendLogin {
        $session ??= $this->session;
        $queryString = http_build_query(data: $queryParameters, encoding_type: PHP_QUERY_RFC3986);

        return $actraBackend->createLogin(viewContext: ViewContextFactory::create(
            route: new Route(path: '/', viewDirectory: __DIR__),
            fileTitle: $fileTitle,
            httpRequest: new HttpRequest(
                host: 'example.com',
                method: $postParameters === [] ? RequestMethodEnum::GET : RequestMethodEnum::POST,
                uri: '/' . $fileTitle . '.html' . ($queryString === '' ? '' : '?' . $queryString),
                queryString: $queryString,
                remoteAddress: BackendLoginTest::IP_ADDRESS,
                queryParameters: $queryParameters,
                postParameters: $postParameters === [] ? [] : [
                    CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session),
                ] + $postParameters,
            ),
            responseSender: $this->responseSender,
            session: $session,
        ));
    }

    private function requestCode(ActraBackend $actraBackend, string $email): string
    {
        $this->assertSame(
            LoginCodeRequestResultEnum::SENT,
            $this->createLogin(actraBackend: $actraBackend)->requestCode(email: $email),
        );

        return $this->responseSender->sendMailsAndGetLoginCode(mailer: $this->mailer);
    }

    public function testCodeIsSentToAKnownAddress(): void
    {
        $email = BackendLoginTest::email(name: 'known');
        $this->testUsers->create(email: $email);

        $this->requestCode(actraBackend: $this->createActraBackend(), email: $email);

        $this->assertCount(1, $this->mailer->sentMails);
        $this->assertSame(1, $this->testUsers->countTokens(email: $email));
    }

    public function testUnknownAddressGetsTheSameAnswer(): void
    {
        $email = BackendLoginTest::email(name: 'unknown');

        $this->assertSame(
            LoginCodeRequestResultEnum::SENT,
            $this->createLogin(actraBackend: $this->createActraBackend())->requestCode(email: $email),
        );
        $this->assertSame([], $this->responseSender->afterResponseCallbacks);
        $this->assertSame(
            [AuthResultEnum::ERROR_UNKNOWN_USER_NAME->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
    }

    public function testCodeRequestAboveTheRateLimit(): void
    {
        $email = BackendLoginTest::email(name: 'rate-limit');
        $this->testUsers->create(email: $email);
        $actraBackend = $this->createActraBackend(loginRateLimit: $this->createLoginRateLimit());

        $this->assertSame(
            LoginCodeRequestResultEnum::SENT,
            $this->createLogin(actraBackend: $actraBackend)->requestCode(email: $email),
        );
        $this->assertSame(
            LoginCodeRequestResultEnum::RATE_LIMITED,
            $this->createLogin(actraBackend: $actraBackend)->requestCode(email: $email),
        );
        $this->assertSame(1, $this->testUsers->countTokens(email: $email));
    }

    public function testCodeLogsInOnAFrontendRoute(): void
    {
        $email = BackendLoginTest::email(name: 'login');
        $this->testUsers->create(email: $email);
        $actraBackend = $this->createActraBackend();
        $code = $this->requestCode(actraBackend: $actraBackend, email: $email);
        $login = $this->createLogin(actraBackend: $actraBackend, fileTitle: 'login_code');

        $this->assertNull($login->getCurrentUser());
        $this->assertSame(LoginCodeResultEnum::LOGGED_IN, $login->logInWithCode(code: $code));
        $this->assertSame($email, $login->getCurrentUser()?->dbAuthUser->email);
        $this->assertSame(
            $email,
            $this->createLogin(actraBackend: $actraBackend, fileTitle: 'home')->getCurrentUser()?->dbAuthUser->email,
        );
        $this->assertSame(
            [AuthResultEnum::SUCCESSFUL_OTP_LOGIN->value],
            $this->testUsers->listLoggedResults(email: $email),
        );

        $login->logOut();

        $this->assertNull($this->createLogin(actraBackend: $actraBackend, fileTitle: 'home')->getCurrentUser());
    }

    public function testCodeOfAnotherSessionIsRefused(): void
    {
        $email = BackendLoginTest::email(name: 'other-session');
        $this->testUsers->create(email: $email);
        $actraBackend = $this->createActraBackend();
        $code = $this->requestCode(actraBackend: $actraBackend, email: $email);
        $otherSession = new Session(storage: new ArraySessionStorage());

        $this->assertSame(
            LoginCodeResultEnum::INVALID,
            $this->createLogin(actraBackend: $actraBackend, session: $otherSession)->logInWithCode(code: $code),
        );
    }

    public function testCodeIsValidOnce(): void
    {
        $email = BackendLoginTest::email(name: 'once');
        $this->testUsers->create(email: $email);
        $actraBackend = $this->createActraBackend();
        $code = $this->requestCode(actraBackend: $actraBackend, email: $email);
        $login = $this->createLogin(actraBackend: $actraBackend);

        $this->assertSame(LoginCodeResultEnum::LOGGED_IN, $login->logInWithCode(code: $code));
        $login->logOut();
        $this->assertSame(
            LoginCodeResultEnum::INVALID,
            $this->createLogin(actraBackend: $actraBackend)->logInWithCode(code: $code),
        );
    }

    public function testExpiredCodeIsRefused(): void
    {
        $email = BackendLoginTest::email(name: 'expired');
        $this->testUsers->create(email: $email);
        $actraBackend = $this->createActraBackend();
        $code = $this->requestCode(actraBackend: $actraBackend, email: $email);
        TestDatabase::connect()->execute(
            sql: 'UPDATE auth_tokens SET created_at=DATE_SUB(NOW(), INTERVAL 16 MINUTE)
                WHERE user_id=(SELECT id FROM auth_users WHERE email=?)',
            parameters: [$email],
        );

        $this->assertSame(
            LoginCodeResultEnum::INVALID,
            $this->createLogin(actraBackend: $actraBackend)->logInWithCode(code: $code),
        );
    }

    public function testPasswordSendsACode(): void
    {
        $email = BackendLoginTest::email(name: 'password');
        $this->testUsers->create(email: $email, password: BackendLoginTest::PASSWORD);
        $actraBackend = $this->createActraBackend();

        $this->assertSame(
            PasswordLoginResultEnum::CREDENTIALS_INVALID,
            $this->createLogin(actraBackend: $actraBackend)->requestCodeWithPassword(email: $email, password: 'wrong'),
        );
        $this->assertSame(
            PasswordLoginResultEnum::CODE_SENT,
            $this->createLogin(actraBackend: $actraBackend)->requestCodeWithPassword(
                email: $email,
                password: BackendLoginTest::PASSWORD,
            ),
        );
        $code = $this->responseSender->sendMailsAndGetLoginCode(mailer: $this->mailer);
        $this->assertSame(
            LoginCodeResultEnum::LOGGED_IN,
            $this->createLogin(actraBackend: $actraBackend)->logInWithCode(code: $code),
        );
    }

    public function testPasswordAboveTheRateLimit(): void
    {
        $email = BackendLoginTest::email(name: 'password-rate-limit');
        $this->testUsers->create(email: $email, password: BackendLoginTest::PASSWORD);
        $actraBackend = $this->createActraBackend(loginRateLimit: $this->createLoginRateLimit());

        $this->createLogin(actraBackend: $actraBackend)->requestCodeWithPassword(email: $email, password: 'wrong');

        $this->assertSame(
            PasswordLoginResultEnum::RATE_LIMITED,
            $this->createLogin(actraBackend: $actraBackend)->requestCodeWithPassword(
                email: $email,
                password: BackendLoginTest::PASSWORD,
            ),
        );
    }

    /**
     * A user with an IP whitelist is no current user from another address.
     */
    public function testCurrentUserFromOutsideTheWhitelistIsNull(): void
    {
        $email = BackendLoginTest::email(name: 'whitelist');
        $this->testUsers->create(email: $email, ipWhitelist: [BackendLoginTest::IP_ADDRESS]);
        $actraBackend = $this->createActraBackend();
        $code = $this->requestCode(actraBackend: $actraBackend, email: $email);
        $this->createLogin(actraBackend: $actraBackend)->logInWithCode(code: $code);
        $otherAddress = ViewContextFactory::create(
            route: new Route(path: '/', viewDirectory: __DIR__),
            fileTitle: 'home',
            httpRequest: new HttpRequest(host: 'example.com', uri: '/home.html', remoteAddress: '192.0.2.99'),
            session: $this->session,
        );

        $this->assertNotNull($this->createLogin(actraBackend: $actraBackend)->getCurrentUser());
        $this->assertNull($actraBackend->createLogin(viewContext: $otherAddress)->getCurrentUser());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function returnPaths(): iterable
    {
        yield 'local path' => ['/de/heizungen.html?page=2', '/de/heizungen.html?page=2'];
        yield 'backend page' => ['/backend/users.html', '/backend/users.html'];
        yield 'other host' => ['https://example.net/', null];
        yield 'protocol-relative URL' => ['//example.net/', null];
    }

    /**
     * The forms on frontend pages keep the validated return path from the email page to the code page.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('returnPaths')]
    public function testReturnPathIsValidatedAndKept(string $returnTo, ?string $expected): void
    {
        $email = BackendLoginTest::email(name: 'return-path');
        $this->testUsers->create(email: $email);
        $actraBackend = $this->createActraBackend();
        $this->assertSame(
            $expected,
            $this->createLogin(actraBackend: $actraBackend, queryParameters: ['returnTo' => $returnTo])
                ->findReturnPath(),
        );

        $loginForm = new LoginForm(login: $this->createLogin(
            actraBackend: $actraBackend,
            queryParameters: ['LoginForm' => ''],
            postParameters: ['email' => $email, 'returnTo' => $returnTo],
        ));
        $this->assertTrue($loginForm->process());
        $this->assertSame($expected, $loginForm->getReturnPath());
        $this->assertNull($loginForm->getLoginRequestId());
        $this->assertSame(
            $expected === null ? '/login_code.html' : '/login_code.html?returnTo=' . rawurlencode(string: $expected),
            BackendLogin::createNextStepUri(
                path: '/login_code.html',
                returnPath: $loginForm->getReturnPath(),
                loginRequestId: $loginForm->getLoginRequestId(),
            ),
        );

        $loginTokenForm = new LoginTokenForm(login: $this->createLogin(
            actraBackend: $actraBackend,
            fileTitle: 'login_code',
            queryParameters: ['LoginTokenForm' => ''],
            postParameters: [
                'token' => $this->responseSender->sendMailsAndGetLoginCode(mailer: $this->mailer),
                'returnTo' => $returnTo,
            ],
        ));
        $this->assertSame($email, $loginTokenForm->process()?->dbAuthUser->email);
        $this->assertSame($expected, $loginTokenForm->getReturnPath());
    }

    public function testNextStepKeepsTheLoginRequest(): void
    {
        $loginRequestId = str_repeat(string: 'c', times: 64);

        $this->assertSame(
            '/login_code.html?returnTo=%2Fde%2Fpage.html&loginRequest=' . $loginRequestId,
            BackendLogin::createNextStepUri(
                path: '/login_code.html',
                returnPath: '/de/page.html',
                loginRequestId: $loginRequestId,
            ),
        );
        $this->assertSame(
            '/login_code.html?loginRequest=' . $loginRequestId,
            BackendLogin::createNextStepUri(path: '/login_code.html', returnPath: null, loginRequestId: $loginRequestId),
        );
    }
}
