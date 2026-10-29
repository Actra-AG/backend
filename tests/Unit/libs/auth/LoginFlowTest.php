<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\form\LoginForm;
use actra\backend\libs\form\LoginPasswordForm;
use actra\backend\libs\form\LoginTokenForm;
use actra\backend\libs\form\PasswordForgottenForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\settings\TokenSendLimit;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\RecordingLogger;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\RejectingMailDomainResolver;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\LoginRateLimit;
use actra\yuf\clock\FixedClock;
use actra\yuf\common\AcceptingMailDomainResolver;
use actra\yuf\common\MailDomainResolver;
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
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * The login forms against the `TestDatabase`: what is logged, counted and sent for each kind of user.
 */
final class LoginFlowTest extends TestCase
{
    private const string PASSWORD = 'correct horse battery staple';
    private const string IP_ADDRESS = '192.0.2.10';
    private TestUsers $testUsers;
    private RecordingResponseSender $responseSender;
    private RecordingMailer $mailer;
    /** `''` while `setUp()` did not get that far (no test database: the test is skipped) */
    private string $rateLimitDirectory = '';

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->responseSender = new RecordingResponseSender();
        $this->mailer = new RecordingMailer();
        $this->rateLimitDirectory = sys_get_temp_dir() . '/backend-rate-limit-' . bin2hex(string: random_bytes(length: 8));
    }

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->rateLimitDirectory === '') {
            return;
        }
        if (is_dir(filename: $this->rateLimitDirectory)) {
            $files = glob(pattern: $this->rateLimitDirectory . '/*');
            foreach ($files === false ? [] : $files as $file) {
                unlink(filename: $file);
            }
            rmdir(directory: $this->rateLimitDirectory);
        } elseif (is_file(filename: $this->rateLimitDirectory)) {
            unlink(filename: $this->rateLimitDirectory);
        }
    }

    /**
     * A login rate limit of one attempt per email address (and many per IP address) in a fresh directory.
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

    private static function email(string $name): string
    {
        return $name . '-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    /**
     * @param array<string, string> $postParameters
     */
    private function createContext(
        string $fileTitle,
        string $formName,
        array $postParameters,
        ?TokenSendLimit $tokenSendLimit = new TokenSendLimit(),
        MailDomainResolver $mailDomainResolver = new AcceptingMailDomainResolver(),
        ?LoginRateLimit $loginRateLimit = null,
    ): BackendViewContext {
        $session = new Session(storage: new ArraySessionStorage());

        return ActraBackendTestInstance::create(
            dbSettings: TestDatabase::settings(),
            tokenSendLimit: $tokenSendLimit,
            loginRateLimit: $loginRateLimit,
            mailer: $this->mailer,
        )->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: $fileTitle,
                httpRequest: new HttpRequest(
                    host: 'example.com',
                    method: RequestMethodEnum::POST,
                    uri: '/backend/' . $fileTitle . '.html?' . $formName,
                    queryString: $formName,
                    remoteAddress: LoginFlowTest::IP_ADDRESS,
                    queryParameters: [$formName => ''],
                    postParameters: [CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $session)]
                        + $postParameters,
                ),
                responseSender: $this->responseSender,
                session: $session,
                mailDomainResolver: $mailDomainResolver,
            ),
        );
    }

    private function createLoginPasswordForm(
        string $email,
        string $password = LoginFlowTest::PASSWORD,
        ?LoginRateLimit $loginRateLimit = null,
    ): LoginPasswordForm {
        $context = $this->createContext(
            fileTitle: 'loginPassword',
            formName: 'LoginPasswordForm',
            postParameters: ['email' => $email, 'password' => $password],
            loginRateLimit: $loginRateLimit,
        );

        return new LoginPasswordForm(context: $context);
    }

    private function passwordLogin(string $email, string $password = LoginFlowTest::PASSWORD): bool
    {
        return $this->createLoginPasswordForm(email: $email, password: $password)->process();
    }

    private function createLoginForm(
        string $email,
        ?TokenSendLimit $tokenSendLimit = new TokenSendLimit(),
        ?LoginRateLimit $loginRateLimit = null,
    ): LoginForm {
        $context = $this->createContext(
            fileTitle: 'login',
            formName: 'LoginForm',
            postParameters: ['email' => $email],
            tokenSendLimit: $tokenSendLimit,
            loginRateLimit: $loginRateLimit,
        );

        return new LoginForm(context: $context);
    }

    private function requestToken(string $email, ?TokenSendLimit $tokenSendLimit = new TokenSendLimit()): bool
    {
        return $this->createLoginForm(email: $email, tokenSendLimit: $tokenSendLimit)->process();
    }

    private function createPasswordForgottenForm(string $email, LoginRateLimit $loginRateLimit): PasswordForgottenForm
    {
        $context = $this->createContext(
            fileTitle: 'passwordForgotten',
            formName: 'PasswordForgottenForm',
            postParameters: ['email' => $email],
            loginRateLimit: $loginRateLimit,
        );

        return new PasswordForgottenForm(context: $context);
    }

    /**
     * The first error of the form (rendered), `null` without error.
     */
    private static function formError(LoginForm|LoginPasswordForm|PasswordForgottenForm $form): ?string
    {
        return $form->errorCollection->hasErrors() ? $form->errorCollection->getFirstError()->render() : null;
    }

    public function testPasswordLoginSendsAToken(): void
    {
        $email = LoginFlowTest::email(name: 'password');
        $this->testUsers->create(email: $email, password: LoginFlowTest::PASSWORD);

        $this->assertTrue($this->passwordLogin(email: $email));
        $this->assertSame(1, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
        $this->assertSame([], $this->testUsers->listLoggedResults(email: $email));
    }

    public function testWrongPasswordIsCountedAndLogged(): void
    {
        $email = LoginFlowTest::email(name: 'wrong');
        $this->testUsers->create(email: $email, password: LoginFlowTest::PASSWORD);

        $this->assertFalse($this->passwordLogin(email: $email, password: 'wrong'));
        $this->assertSame(1, $this->testUsers->getWrongLoginAttempts(email: $email));
        $this->assertSame(
            [AuthResultEnum::ERROR_WRONG_PASSWORD->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
        $this->assertSame(0, $this->testUsers->countTokens(email: $email));
    }

    /**
     * @return iterable<string, array{array{password?: ?string, isActive?: bool, withRights?: bool,
     *     ipWhitelist?: list<string>, wrongLoginAttempts?: int}|null, AuthResultEnum}>
     */
    public static function rejectedPasswordLogins(): iterable
    {
        yield 'unknown user' => [null, AuthResultEnum::ERROR_UNKNOWN_USER_NAME];
        yield 'user without password' => [['password' => null], AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE];
        yield 'inactive user' => [['isActive' => false], AuthResultEnum::ERROR_INACTIVE];
        yield 'user without rights' => [['withRights' => false], AuthResultEnum::ERROR_INACTIVE];
        yield 'other IP address' => [['ipWhitelist' => ['192.0.2.99']], AuthResultEnum::ERROR_IP_NOT_ALLOWED];
        yield 'out tried' => [['wrongLoginAttempts' => 5], AuthResultEnum::ERROR_OUT_TRIED];
    }

    /**
     * @param array{password?: ?string, isActive?: bool, withRights?: bool, ipWhitelist?: list<string>,
     *     wrongLoginAttempts?: int}|null $user
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedPasswordLogins')]
    public function testRejectedPasswordLoginIsLogged(?array $user, AuthResultEnum $expected): void
    {
        $email = LoginFlowTest::email(name: 'rejected');
        if ($user !== null) {
            $this->testUsers->create(
                email: $email,
                password: array_key_exists(key: 'password', array: $user) ? $user['password'] : LoginFlowTest::PASSWORD,
                isActive: $user['isActive'] ?? true,
                withRights: $user['withRights'] ?? true,
                ipWhitelist: $user['ipWhitelist'] ?? [],
                wrongLoginAttempts: $user['wrongLoginAttempts'] ?? 0,
            );
        }

        $this->assertFalse($this->passwordLogin(email: $email));
        $this->assertSame([$expected->value], $this->testUsers->listLoggedResults(email: $email));
        $this->assertSame(0, $this->testUsers->countTokens(email: $email));
        $this->assertSame([], $this->responseSender->afterResponseCallbacks);
    }

    public function testEmailOfARejectedMailDomainIsInvalid(): void
    {
        $email = LoginFlowTest::email(name: 'null-mx');
        $this->testUsers->create(email: $email);
        $context = $this->createContext(
            fileTitle: 'login',
            formName: 'LoginForm',
            postParameters: ['email' => $email],
            mailDomainResolver: new RejectingMailDomainResolver(),
        );

        $this->assertFalse(new LoginForm(context: $context)->process());
        $this->assertSame(0, $this->testUsers->countTokens(email: $email));
        $this->assertSame([], $this->responseSender->afterResponseCallbacks);
    }

    public function testTokenRequestOfAUserWithoutPasswordSendsAToken(): void
    {
        $email = LoginFlowTest::email(name: 'token');
        $this->testUsers->create(email: $email);

        $this->assertTrue($this->requestToken(email: $email));
        $this->assertSame(1, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
        $this->assertSame([], $this->testUsers->listLoggedResults(email: $email));
    }

    /**
     * @return iterable<string, array{array{password?: ?string, isActive?: bool, withRights?: bool,
     *     ipWhitelist?: list<string>, wrongLoginAttempts?: int}|null, AuthResultEnum}>
     */
    public static function rejectedTokenRequests(): iterable
    {
        yield 'unknown user' => [null, AuthResultEnum::ERROR_UNKNOWN_USER_NAME];
        yield 'user with password' => [['password' => LoginFlowTest::PASSWORD], AuthResultEnum::ERROR_NO_PASSWORD];
        yield 'inactive user' => [['isActive' => false], AuthResultEnum::ERROR_INACTIVE];
        yield 'user without rights' => [['withRights' => false], AuthResultEnum::ERROR_INACTIVE];
        yield 'other IP address' => [['ipWhitelist' => ['192.0.2.99']], AuthResultEnum::ERROR_IP_NOT_ALLOWED];
        yield 'out tried' => [['wrongLoginAttempts' => 5], AuthResultEnum::ERROR_OUT_TRIED];
    }

    /**
     * The form answers the same in every case (`true`), so it does not tell whether the address exists.
     *
     * @param array{password?: ?string, isActive?: bool, withRights?: bool, ipWhitelist?: list<string>,
     *     wrongLoginAttempts?: int}|null $user
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedTokenRequests')]
    public function testRejectedTokenRequestIsLoggedAndAnsweredTheSame(?array $user, AuthResultEnum $expected): void
    {
        $email = LoginFlowTest::email(name: 'rejected-token');
        if ($user !== null) {
            $this->testUsers->create(
                email: $email,
                password: $user['password'] ?? null,
                isActive: $user['isActive'] ?? true,
                withRights: $user['withRights'] ?? true,
                ipWhitelist: $user['ipWhitelist'] ?? [],
                wrongLoginAttempts: $user['wrongLoginAttempts'] ?? 0,
            );
        }

        $this->assertTrue($this->requestToken(email: $email));
        $this->assertSame([$expected->value], $this->testUsers->listLoggedResults(email: $email));
        $this->assertSame(0, $this->testUsers->countTokens(email: $email));
        $this->assertSame([], $this->responseSender->afterResponseCallbacks);
    }

    /**
     * Registers tokens of a user as if they had been sent `$minutesAgo` minutes ago.
     */
    private function createTokens(string $email, AuthTokenTypeEnum $type, int $amount, int $minutesAgo): void
    {
        $repositories = ActraBackendTestInstance::create(dbSettings: TestDatabase::settings())->getRepositories();
        $dbAuthUser = $repositories->users()->selectByEmail(email: $email)
            ?? throw new LogicException(message: 'The user was not created.');
        $clock = new FixedClock(now: new DateTimeImmutable(datetime: '-' . $minutesAgo . ' minutes'));
        for ($i = 0; $i < $amount; $i++) {
            $repositories->tokens()->createToken(
                dbAuthUser: $dbAuthUser,
                authTokenTypeEnum: $type,
                clientData: new ClientData(userAgent: '', ipAddress: LoginFlowTest::IP_ADDRESS, sessionId: ''),
                clock: $clock,
            );
        }
    }

    /**
     * Above the limit the form answers the same (`true`), but sends nothing.
     */
    public function testTokenRequestAboveTheSendLimitSendsNothing(): void
    {
        $email = LoginFlowTest::email(name: 'limit');
        $this->testUsers->create(email: $email);
        $tokenSendLimit = new TokenSendLimit(maxTokens: 2, withinMinutes: 15);

        $this->assertTrue($this->requestToken(email: $email, tokenSendLimit: $tokenSendLimit));
        $this->assertTrue($this->requestToken(email: $email, tokenSendLimit: $tokenSendLimit));
        $this->assertTrue($this->requestToken(email: $email, tokenSendLimit: $tokenSendLimit));

        $this->assertSame(2, $this->testUsers->countTokens(email: $email));
        $this->assertCount(2, $this->responseSender->afterResponseCallbacks);
    }

    public function testDefaultSendLimitIsFiveTokens(): void
    {
        $email = LoginFlowTest::email(name: 'default-limit');
        $this->testUsers->create(email: $email);
        $this->createTokens(email: $email, type: AuthTokenTypeEnum::LOGIN, amount: 4, minutesAgo: 1);

        $this->assertTrue($this->requestToken(email: $email));
        $this->assertTrue($this->requestToken(email: $email));

        $this->assertSame(5, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
    }

    public function testTokensBeforeTheWindowDoNotCount(): void
    {
        $email = LoginFlowTest::email(name: 'old-tokens');
        $this->testUsers->create(email: $email);
        $this->createTokens(email: $email, type: AuthTokenTypeEnum::LOGIN, amount: 5, minutesAgo: 16);

        $this->assertTrue($this->requestToken(email: $email));

        $this->assertSame(6, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
    }

    public function testSendLimitCountsPerType(): void
    {
        $email = LoginFlowTest::email(name: 'other-type');
        $this->testUsers->create(email: $email);
        $this->createTokens(email: $email, type: AuthTokenTypeEnum::PASSWORD, amount: 5, minutesAgo: 1);

        $this->assertTrue($this->requestToken(email: $email));

        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
    }

    public function testNoSendLimitWithNull(): void
    {
        $email = LoginFlowTest::email(name: 'no-limit');
        $this->testUsers->create(email: $email);
        $this->createTokens(email: $email, type: AuthTokenTypeEnum::LOGIN, amount: 5, minutesAgo: 1);

        $this->assertTrue($this->requestToken(email: $email, tokenSendLimit: null));

        $this->assertSame(6, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
    }

    /**
     * The password is correct, so the form succeeds; the code sent before stays valid in the session it was sent to.
     */
    public function testPasswordLoginAboveTheSendLimitSendsNoToken(): void
    {
        $email = LoginFlowTest::email(name: 'password-limit');
        $this->testUsers->create(email: $email, password: LoginFlowTest::PASSWORD);
        $this->createTokens(email: $email, type: AuthTokenTypeEnum::LOGIN, amount: 5, minutesAgo: 1);

        $this->assertTrue($this->passwordLogin(email: $email));

        $this->assertSame(5, $this->testUsers->countTokens(email: $email));
        $this->assertSame([], $this->responseSender->afterResponseCallbacks);
    }

    /**
     * Requests a login code and logs in with it in the same session.
     *
     * @return array{?MyAuthUser, BackendViewContext} The result of the token form and the context of its request
     */
    private function loginWithRequestedToken(string $email, ?LoginRateLimit $loginRateLimit = null): array
    {
        $requestContext = $this->createContext(
            fileTitle: 'login',
            formName: 'LoginForm',
            postParameters: ['email' => $email],
            loginRateLimit: $loginRateLimit,
        );
        new LoginForm(context: $requestContext)->process();
        $token = $this->responseSender->sendMailsAndGetLoginCode(mailer: $this->mailer);
        $tokenViewContext = ViewContextFactory::create(
            route: new Route(path: '/backend/', viewDirectory: __DIR__),
            fileTitle: 'loginToken',
            httpRequest: new HttpRequest(
                host: 'example.com',
                method: RequestMethodEnum::POST,
                uri: '/backend/loginToken.html?LoginTokenForm',
                queryString: 'LoginTokenForm',
                remoteAddress: LoginFlowTest::IP_ADDRESS,
                queryParameters: ['LoginTokenForm' => ''],
                postParameters: [
                    CsrfTokenSource::FIELD_NAME => ViewContextFactory::csrfToken(session: $requestContext->session),
                    'token' => $token,
                ],
            ),
            session: $requestContext->session,
        );
        $tokenContext = ActraBackendTestInstance::create(
            dbSettings: TestDatabase::settings(),
            loginRateLimit: $loginRateLimit,
        )->createContext(viewContext: $tokenViewContext);

        return [new LoginTokenForm(context: $tokenContext)->process(), $tokenContext];
    }

    public function testTokenLogsTheUserIn(): void
    {
        $email = LoginFlowTest::email(name: 'login');
        $this->testUsers->create(email: $email);

        [$myAuthUser, $tokenContext] = $this->loginWithRequestedToken(email: $email);

        $this->assertNotNull($myAuthUser);
        $this->assertTrue($tokenContext->authSession->isLoggedIn());
        $this->assertSame(
            [AuthResultEnum::SUCCESSFUL_OTP_LOGIN->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
    }

    /**
     * The code was sent after a counted request, so the login with it is not counted again and the claimed code is not
     * lost to the rate limit.
     */
    public function testTokenLoginIsNotCountedByTheRateLimit(): void
    {
        $email = LoginFlowTest::email(name: 'login-rate-limit');
        $this->testUsers->create(email: $email);

        [$myAuthUser] = $this->loginWithRequestedToken(email: $email, loginRateLimit: $this->createLoginRateLimit());

        $this->assertNotNull($myAuthUser);
        $this->assertSame(
            [AuthResultEnum::SUCCESSFUL_OTP_LOGIN->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
    }

    /**
     * Blocked before the user is loaded: the right password is not checked, no wrong attempt counted, nothing sent.
     */
    public function testPasswordLoginAboveTheRateLimitIsRejectedAndLogged(): void
    {
        $email = LoginFlowTest::email(name: 'rate-limit');
        $this->testUsers->create(email: $email, password: LoginFlowTest::PASSWORD);
        $loginRateLimit = $this->createLoginRateLimit();

        $this->createLoginPasswordForm(email: $email, password: 'wrong', loginRateLimit: $loginRateLimit)->process();
        $form = $this->createLoginPasswordForm(email: $email, loginRateLimit: $loginRateLimit);

        $this->assertFalse($form->process());
        $this->assertSame(
            'Zu viele Versuche. Bitte versuchen Sie es in einigen Minuten erneut.',
            LoginFlowTest::formError(form: $form),
        );
        $this->assertSame(
            [AuthResultEnum::ERROR_WRONG_PASSWORD->value, AuthResultEnum::ERROR_RATE_LIMITED->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
        $this->assertSame(1, $this->testUsers->getWrongLoginAttempts(email: $email));
        $this->assertSame(0, $this->testUsers->countTokens(email: $email));
    }

    /**
     * Unknown addresses are counted like known ones and get the same message, so the limit does not tell which exist.
     */
    public function testRateLimitAnswersUnknownAddressesTheSame(): void
    {
        $email = LoginFlowTest::email(name: 'rate-limit-unknown');
        $loginRateLimit = $this->createLoginRateLimit();

        $this->assertSame(
            'Die eingegebenen Zugangsdaten sind ungültig.',
            LoginFlowTest::formError(
                form: $this->processed(
                    form: $this->createLoginPasswordForm(email: $email, loginRateLimit: $loginRateLimit),
                ),
            ),
        );
        $this->assertSame(
            'Zu viele Versuche. Bitte versuchen Sie es in einigen Minuten erneut.',
            LoginFlowTest::formError(
                form: $this->processed(
                    form: $this->createLoginPasswordForm(email: $email, loginRateLimit: $loginRateLimit),
                ),
            ),
        );
        $this->assertSame(
            [AuthResultEnum::ERROR_UNKNOWN_USER_NAME->value, AuthResultEnum::ERROR_RATE_LIMITED->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
    }

    private function processed(LoginPasswordForm $form): LoginPasswordForm
    {
        $form->process();

        return $form;
    }

    public function testTokenRequestAboveTheRateLimitSendsNothing(): void
    {
        $email = LoginFlowTest::email(name: 'token-rate-limit');
        $this->testUsers->create(email: $email);
        $loginRateLimit = $this->createLoginRateLimit();

        $this->assertTrue($this->createLoginForm(email: $email, loginRateLimit: $loginRateLimit)->process());
        $form = $this->createLoginForm(email: $email, loginRateLimit: $loginRateLimit);

        $this->assertFalse($form->process());
        $this->assertSame(
            'Zu viele Versuche. Bitte versuchen Sie es in einigen Minuten erneut.',
            LoginFlowTest::formError(form: $form),
        );
        $this->assertSame([AuthResultEnum::ERROR_RATE_LIMITED->value], $this->testUsers->listLoggedResults(email: $email));
        $this->assertSame(1, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
    }

    public function testPasswordResetRequestAboveTheRateLimitSendsNothing(): void
    {
        $email = LoginFlowTest::email(name: 'reset-rate-limit');
        $this->testUsers->create(email: $email, password: LoginFlowTest::PASSWORD);
        $loginRateLimit = $this->createLoginRateLimit();

        $this->assertTrue(
            $this->createPasswordForgottenForm(email: $email, loginRateLimit: $loginRateLimit)
                ->validateAndSendTokenEmail(),
        );
        $form = $this->createPasswordForgottenForm(email: $email, loginRateLimit: $loginRateLimit);

        $this->assertFalse($form->validateAndSendTokenEmail());
        $this->assertSame(
            'Zu viele Versuche. Bitte versuchen Sie es in einigen Minuten erneut.',
            LoginFlowTest::formError(form: $form),
        );
        $this->assertSame(1, $this->testUsers->countTokens(email: $email));
        $this->assertCount(1, $this->responseSender->afterResponseCallbacks);
    }

    /**
     * `failMode: CLOSED`: an attempt that cannot be counted (directory not writable) is rejected and the error logged.
     */
    public function testRateLimitFailsClosed(): void
    {
        $email = LoginFlowTest::email(name: 'rate-limit-closed');
        $this->testUsers->create(email: $email, password: LoginFlowTest::PASSWORD);
        touch(filename: $this->rateLimitDirectory); // A file where the directory should be
        $logger = new RecordingLogger();
        $loginRateLimit = new LoginRateLimit(
            rateLimiter: new RateLimiter(
                directory: $this->rateLimitDirectory,
                secret: str_repeat(string: 's', times: 32),
                purgeProbabilityPercent: 0,
            ),
            failMode: RateLimitFailModeEnum::CLOSED,
            logger: $logger,
        );

        $this->assertFalse($this->createLoginPasswordForm(email: $email, loginRateLimit: $loginRateLimit)->process());
        $this->assertSame([AuthResultEnum::ERROR_RATE_LIMITED->value], $this->testUsers->listLoggedResults(email: $email));
        $this->assertNotSame([], [...$logger->messages, ...$logger->exceptions]);
    }
}
