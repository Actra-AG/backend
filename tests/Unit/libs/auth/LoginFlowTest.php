<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\form\LoginForm;
use actra\backend\libs\form\LoginPasswordForm;
use actra\backend\libs\form\LoginTokenForm;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\settings\TokenSendLimit;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\clock\FixedClock;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\Route;
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

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->responseSender = new RecordingResponseSender();
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
    ): BackendViewContext {
        return ActraBackendTestInstance::create(
            dbSettings: TestDatabase::settings(),
            tokenSendLimit: $tokenSendLimit,
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
                    postParameters: $postParameters,
                ),
                responseSender: $this->responseSender,
            ),
        );
    }

    private function passwordLogin(string $email, string $password = LoginFlowTest::PASSWORD): bool
    {
        $context = $this->createContext(
            fileTitle: 'loginPassword',
            formName: 'LoginPasswordForm',
            postParameters: ['email' => $email, 'password' => $password],
        );

        return new LoginPasswordForm(context: $context)->process();
    }

    private function requestToken(string $email, ?TokenSendLimit $tokenSendLimit = new TokenSendLimit()): bool
    {
        $context = $this->createContext(
            fileTitle: 'login',
            formName: 'LoginForm',
            postParameters: ['email' => $email],
            tokenSendLimit: $tokenSendLimit,
        );

        return new LoginForm(context: $context)->process();
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

    public function testTokenLogsTheUserIn(): void
    {
        $email = LoginFlowTest::email(name: 'login');
        $this->testUsers->create(email: $email);
        $requestContext = $this->createContext(
            fileTitle: 'login',
            formName: 'LoginForm',
            postParameters: ['email' => $email],
        );
        new LoginForm(context: $requestContext)->process();
        $token = $requestContext->session->getString(key: 'auth_token_login') ?? '';
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
                postParameters: ['token' => $token],
            ),
            session: $requestContext->session,
        );
        $tokenContext = ActraBackendTestInstance::create(dbSettings: TestDatabase::settings())->createContext(
            viewContext: $tokenViewContext,
        );

        $this->assertNotNull(new LoginTokenForm(context: $tokenContext)->process());
        $this->assertTrue($tokenContext->authSession->isLoggedIn());
        $this->assertSame(
            [AuthResultEnum::SUCCESSFUL_OTP_LOGIN->value],
            $this->testUsers->listLoggedResults(email: $email),
        );
    }
}
