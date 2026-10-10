<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * Renders every view of the backend in both languages, logged out and logged in where the view allows it: a missing
 * replacement or a broken template fails (`TemplateException`), as does a placeholder left in the output.
 */
final class ViewRenderTest extends TestCase
{
    private const array LOGGED_OUT_VIEWS = [
        'login',
        'loginPassword',
        'loginToken',
        'loginPasswordToken',
        'logout',
        'passwordForgotten',
        'passwordForgottenRes',
        'passwordReset',
        'passwordResetRes',
    ];
    /** login and loginToken redirect a logged-in user, profileCreatePassword is rendered without password */
    private const array LOGGED_IN_VIEWS = [
        'loginPassword',
        'loginPasswordToken',
        'logout',
        'passwordForgotten',
        'passwordForgottenRes',
        'passwordReset',
        'passwordResetRes',
        'notification',
        'notificationSend',
        'notifications',
        'profile',
        'profileChangePassword',
        'profileGenerateApiKey',
        'profileRemoveApiKey',
        'profileRemovePassword',
        'tokens',
        'user',
        'userAdd',
        'userDelete',
        'userGenerateApiKey',
        'userInvite',
        'userMod',
        'userRemoveApiKey',
        'users',
        'visits',
    ];
    private BackendPageRenderer $renderer;
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
    }

    private static function email(string $name): string
    {
        return $name . '-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    /**
     * @return iterable<string, array{string, string, ?bool}> Language, file title and login (`null`: logged out,
     *     otherwise whether the user has a password)
     */
    public static function views(): iterable
    {
        foreach (['de', 'en'] as $languageCode) {
            foreach (ViewRenderTest::LOGGED_OUT_VIEWS as $fileTitle) {
                yield $languageCode . ' ' . $fileTitle . ' logged out' => [$languageCode, $fileTitle, null];
            }
            foreach (ViewRenderTest::LOGGED_IN_VIEWS as $fileTitle) {
                yield $languageCode . ' ' . $fileTitle . ' logged in' => [$languageCode, $fileTitle, true];
            }
            yield $languageCode . ' profileCreatePassword logged in' => [$languageCode, 'profileCreatePassword', false];
        }
    }

    #[DataProvider('views')]
    public function testViewIsRendered(string $languageCode, string $fileTitle, ?bool $withPassword): void
    {
        $session = new Session(storage: new ArraySessionStorage());
        $userId = $withPassword === null ? null : $this->logIn(session: $session, withPassword: $withPassword);

        $html = $this->renderer->render(
            languageCode: $languageCode,
            fileTitle: $fileTitle,
            pathVars: $this->createPathVars(fileTitle: $fileTitle, userId: $userId),
            session: $session,
        );

        $this->assertStringContainsString(' lang="' . $languageCode . '">', $html);
        $this->assertStringContainsString('</html>', $html);
        // The default message of notificationSend keeps [firstName] and [lastName] for the recipients
        $this->assertDoesNotMatchRegularExpression(
            '/\[[a-zA-Z][a-zA-Z0-9]*]/',
            (string) preg_replace(pattern: '/<textarea.*?<\/textarea>/s', replacement: '', subject: $html),
        );
    }

    public function testPasswordResetResLinksToTheLogin(): void
    {
        $html = $this->renderer->render(
            languageCode: 'de',
            fileTitle: 'passwordResetRes',
            pathVars: [],
            session: new Session(storage: new ArraySessionStorage()),
        );

        $this->assertStringContainsString('<a href="/backend/loginPassword.html">Anmelden</a>', $html);
    }

    /**
     * The name column of the user list and of the notification list: "[firstName] [lastName]" filled.
     */
    #[TestWith(['users'])]
    #[TestWith(['notifications'])]
    public function testNameColumnShowsFirstAndLastName(string $fileTitle): void
    {
        $session = new Session(storage: new ArraySessionStorage());
        $email = ViewRenderTest::email(name: 'name');
        $userId = $this->logIn(session: $session, withPassword: true, email: $email);
        $this->renderer->actraBackend->getRepositories()->notifications()->insert(
            authGroupId: 1,
            sentByUserId: $userId,
            subject: 'Subject',
            message: 'Message',
        );

        $html = $this->renderer->render(
            languageCode: 'de',
            fileTitle: $fileTitle,
            pathVars: [],
            session: $session,
            postParameters: $fileTitle === 'users' ? ['searchQuery' => $email] : [],
        );

        $this->assertStringContainsString('>Test User<', $html);
        $this->assertStringNotContainsString('[lastName]', $html);
    }

    /**
     * Logs in a new user with backend rights, an IP whitelist and an API key; returns the ID of the user.
     */
    private function logIn(Session $session, bool $withPassword, ?string $email = null): int
    {
        $userId = $this->testUsers->create(
            email: $email ?? ViewRenderTest::email(name: 'render'),
            password: $withPassword ? 'correct horse battery staple' : null,
            ipWhitelist: [BackendPageRenderer::IP_ADDRESS],
        );
        $this->renderer->actraBackend->getRepositories()->apiKeys()->createForUserId(userId: $userId);
        $this->renderer->logIn(session: $session, userId: $userId);

        return $userId;
    }

    /**
     * @return list<string>
     */
    private function createPathVars(string $fileTitle, ?int $userId): array
    {
        $repositories = $this->renderer->actraBackend->getRepositories();

        return match ($fileTitle) {
            'notification' => [
                (string) $repositories->notifications()->insert(
                    authGroupId: 1,
                    sentByUserId: $userId ?? throw new LogicException(message: 'The view needs a login.'),
                    subject: 'Subject',
                    message: "Line 1\nLine 2",
                ),
            ],
            'passwordReset' => [
                $repositories->tokens()->createToken(
                    dbAuthUser: $repositories->users()->selectById(
                        id: $this->testUsers->create(email: ViewRenderTest::email(name: 'reset')),
                    ) ?? throw new LogicException(message: 'The user was not created.'),
                    authTokenTypeEnum: AuthTokenTypeEnum::PASSWORD,
                    clientData: BackendPageRenderer::createClientData(),
                ),
            ],
            'user', 'userDelete', 'userGenerateApiKey', 'userInvite', 'userMod', 'userRemoveApiKey' => [
                (string) $this->createOtherUser(),
            ],
            default => [],
        };
    }

    /**
     * A user with an IP whitelist and an API key, for the views of another user.
     */
    private function createOtherUser(): int
    {
        $userId = $this->testUsers->create(
            email: ViewRenderTest::email(name: 'other'),
            ipWhitelist: ['192.0.2.30'],
        );
        $this->renderer->actraBackend->getRepositories()->apiKeys()->createForUserId(userId: $userId);

        return $userId;
    }
}
