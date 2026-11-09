<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\BackendPaths;
use actra\backend\i18n\BackendMessages;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\exception\NotFoundException;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Without password login (`ActraBackendSettings::$passwordLogin`) the password pages answer 404 and no page links to
 * them or mentions a password; with the default the profile offers the password as before.
 */
final class PasswordLoginTest extends TestCase
{
    private const string PASSWORD = 'correct horse battery staple';
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
    }

    private static function email(): string
    {
        return 'password-login-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    private function createUserWithPassword(): int
    {
        return $this->testUsers->create(email: PasswordLoginTest::email(), password: PasswordLoginTest::PASSWORD);
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function passwordPageRequests(): iterable
    {
        foreach (BackendPaths::PASSWORD_FILE_TITLES as $fileTitle) {
            foreach ([false, true] as $isPost) {
                foreach ([false, true] as $isLoggedIn) {
                    yield $fileTitle . ($isPost ? ' POST' : ' GET') . ($isLoggedIn ? ' logged in' : '')
                        => [$fileTitle, $isPost, $isLoggedIn];
                }
            }
        }
    }

    #[DataProvider('passwordPageRequests')]
    public function testPasswordPageIsNotFound(string $fileTitle, bool $isPost, bool $isLoggedIn): void
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), passwordLogin: false);
        $session = new Session(storage: new ArraySessionStorage());
        if ($isLoggedIn) {
            $renderer->logIn(session: $session, userId: $this->createUserWithPassword());
        }

        $this->expectException(NotFoundException::class);
        $renderer->render(
            languageCode: 'de',
            fileTitle: $fileTitle,
            pathVars: $fileTitle === 'passwordReset' ? [str_repeat(string: 'a', times: 32)] : [],
            session: $session,
            postParameters: $isPost ? ['email' => 'someone@example.com', 'password' => 'some password'] : [],
            formName: $isPost ? 'Form' : null,
        );
    }

    private function renderProfile(bool $passwordLogin): string
    {
        $renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings(), passwordLogin: $passwordLogin);
        $session = new Session(storage: new ArraySessionStorage());
        $renderer->logIn(session: $session, userId: $this->createUserWithPassword());

        return $renderer->render(languageCode: 'de', fileTitle: 'profile', pathVars: [], session: $session);
    }

    public function testProfileShowsThePasswordWithPasswordLogin(): void
    {
        $html = $this->renderProfile(passwordLogin: true);

        $this->assertStringContainsString(BackendMessages::german()->profile->passwordProtectionHeading, $html);
        $this->assertStringContainsString('/backend/loginPassword.html', $html);
        $this->assertStringContainsString('/backend/profileChangePassword.html', $html);
    }

    public function testProfileHasNoPasswordSectionWithoutPasswordLogin(): void
    {
        $html = $this->renderProfile(passwordLogin: false);

        $this->assertStringContainsString('</html>', $html);
        $this->assertStringNotContainsString(BackendMessages::german()->profile->passwordProtectionHeading, $html);
        $this->assertDoesNotMatchRegularExpression('/passw/i', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function loginPages(): iterable
    {
        foreach (['de', 'en'] as $languageCode) {
            foreach (['login', 'loginToken'] as $fileTitle) {
                yield $languageCode . ' ' . $fileTitle => [$languageCode, $fileTitle];
            }
        }
    }

    #[DataProvider('loginPages')]
    public function testLoginPagesHaveNoPasswordLinkWithoutPasswordLogin(string $languageCode, string $fileTitle): void
    {
        $html = new BackendPageRenderer(dbSettings: TestDatabase::settings(), passwordLogin: false)->render(
            languageCode: $languageCode,
            fileTitle: $fileTitle,
            pathVars: [],
            session: new Session(storage: new ArraySessionStorage()),
        );

        $this->assertStringContainsString('</html>', $html);
        $this->assertDoesNotMatchRegularExpression('/passw/i', $html);
    }

    /**
     * The default subject of the welcome email and the intro of the login code mail without password login mention no
     * password in any language.
     */
    public function testInvitationAndLoginCodeMailMentionNoPassword(): void
    {
        foreach (['en', 'de', 'fr', 'it'] as $languageCode) {
            $messages = BackendMessages::forLanguageCode(languageCode: $languageCode);
            foreach ([$messages->user->inviteDefaultSubject, $messages->email->loginTokenIntroPasswordLogin] as $text) {
                $this->assertDoesNotMatchRegularExpression('/passw|mot de passe/i', $text, $languageCode);
            }
        }
    }
}
