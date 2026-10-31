<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\auth\AuthSession;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionPreferredLanguage;
use PHPUnit\Framework\TestCase;

/**
 * The language switcher of the page header and its page `language.html`: the session language for everybody, the
 * profile language too for a logged-in user (it wins over the session).
 */
final class LanguageSwitchTest extends TestCase
{
    private BackendPageRenderer $renderer;
    private TestUsers $testUsers;
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->renderer = new BackendPageRenderer(dbSettings: TestDatabase::settings());
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    private static function email(): string
    {
        return 'switch-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    /**
     * Requests the language switch and returns the target of its redirect.
     *
     * @param array<string, string> $queryParameters
     */
    private function switchLanguage(array $queryParameters, bool $withToken): string
    {
        $responseSender = new RecordingResponseSender();
        if ($withToken) {
            $queryParameters[CsrfTokenSource::FIELD_NAME] = ViewContextFactory::csrfToken(session: $this->session);
        }
        try {
            $this->renderer->render(
                languageCode: 'de',
                fileTitle: 'language',
                pathVars: [],
                session: $this->session,
                queryParameters: $queryParameters,
                responseSender: $responseSender,
            );
            LanguageSwitchTest::fail('The language switch does not redirect.');
        } catch (ResponseSentException) {
        }

        return $responseSender->sentResponse?->getHeader(key: 'Location') ?? '';
    }

    private function getProfileLanguage(int $userId): ?string
    {
        return $this->renderer->actraBackend->getRepositories()->users()->selectById(id: $userId)?->languageCode;
    }

    public function testWithoutLoginTheSessionGetsTheLanguage(): void
    {
        $location = $this->switchLanguage(
            queryParameters: ['language' => 'en', 'returnTo' => '/backend/passwordForgotten.html'],
            withToken: false,
        );

        $this->assertSame('https://example.com/backend/passwordForgotten.html', $location);
        $this->assertSame('en', new SessionPreferredLanguage(session: $this->session)->getCode());
    }

    public function testLoggedInUserSavesTheLanguageInTheProfile(): void
    {
        $userId = $this->testUsers->create(email: LanguageSwitchTest::email(), languageCode: 'de');
        $this->renderer->logIn(session: $this->session, userId: $userId);

        $location = $this->switchLanguage(
            queryParameters: ['language' => 'en', 'returnTo' => '/backend/users.html'],
            withToken: true,
        );

        $this->assertSame('https://example.com/backend/users.html', $location);
        $this->assertSame('en', $this->getProfileLanguage(userId: $userId));
        $this->assertSame('en', new SessionPreferredLanguage(session: $this->session)->getCode());
    }

    public function testWithoutTheTokenOfTheLinkTheProfileStays(): void
    {
        $userId = $this->testUsers->create(email: LanguageSwitchTest::email(), languageCode: 'de');
        $this->renderer->logIn(session: $this->session, userId: $userId);

        $this->switchLanguage(queryParameters: ['language' => 'en'], withToken: false);

        $this->assertSame('de', $this->getProfileLanguage(userId: $userId));
    }

    public function testUnknownLanguageChangesNothing(): void
    {
        $userId = $this->testUsers->create(email: LanguageSwitchTest::email(), languageCode: 'de');
        $this->renderer->logIn(session: $this->session, userId: $userId);

        $location = $this->switchLanguage(
            queryParameters: ['language' => 'fr', 'returnTo' => 'https://example.org/'],
            withToken: true,
        );

        // Not a local path: back to the backend
        $this->assertSame('https://example.com/backend/login.html', $location);
        $this->assertSame('de', $this->getProfileLanguage(userId: $userId));
        $this->assertNull(new SessionPreferredLanguage(session: $this->session)->getCode());
    }

    public function testImpersonationSavesTheProfileOfTheImpersonatingUser(): void
    {
        $adminId = $this->testUsers->create(email: LanguageSwitchTest::email(), languageCode: 'de');
        $impersonatedId = $this->testUsers->create(email: LanguageSwitchTest::email(), languageCode: 'de');
        $this->renderer->logIn(session: $this->session, userId: $adminId);
        $authSession = new AuthSession(session: $this->session);
        $authSession->logIn(
            authSessionId: $this->renderer->actraBackend->getRepositories()->sessions()->insert(
                parentId: $authSession->getAuthSessionId(),
                userId: $impersonatedId,
                clientData: BackendPageRenderer::createClientData(),
            ),
        );

        $this->switchLanguage(queryParameters: ['language' => 'en'], withToken: true);

        $this->assertSame('en', $this->getProfileLanguage(userId: $adminId));
        $this->assertSame('de', $this->getProfileLanguage(userId: $impersonatedId));
    }

    public function testPageHeaderLinksTheSwitchWithTheTokenOfTheSession(): void
    {
        $this->renderer->logIn(
            session: $this->session,
            userId: $this->testUsers->create(email: LanguageSwitchTest::email()),
        );

        $html = $this->renderer->render(languageCode: 'en', fileTitle: 'users', pathVars: [], session: $this->session);

        $token = rawurlencode(string: ViewContextFactory::csrfToken(session: $this->session));
        $this->assertStringContainsString(
            '<a href="/backend/language.html?' . CsrfTokenSource::FIELD_NAME . '=' . $token
            . '&amp;language=de&amp;returnTo=%2Fbackend%2Fusers.html" lang="de"',
            $html,
        );
        $this->assertStringContainsString('<span lang="en" aria-current="true">English</span>', $html);
    }

    public function testLoginPageLinksTheSwitchWithoutToken(): void
    {
        $html = $this->renderer->render(languageCode: 'de', fileTitle: 'login', pathVars: [], session: $this->session);

        $this->assertStringContainsString(
            '<a href="/backend/language.html?language=en&amp;returnTo=%2Fbackend%2Flogin.html" lang="en"',
            $html,
        );
    }
}
