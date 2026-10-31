<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * The language of the request from the profile of the logged-in user (`ActraBackend::createRequestLanguageProvider()`).
 */
final class ProfileLanguageProviderTest extends TestCase
{
    private ActraBackend $actraBackend;
    private RequestLanguageProvider $provider;
    private TestUsers $testUsers;
    private HttpRequest $httpRequest;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
        $this->actraBackend = ActraBackendTestInstance::create(dbSettings: TestDatabase::settings());
        $this->provider = $this->actraBackend->createRequestLanguageProvider();
        $this->httpRequest = new HttpRequest(host: 'example.com');
    }

    private static function email(): string
    {
        return 'language-' . bin2hex(string: random_bytes(length: 4)) . '@example.com';
    }

    /**
     * A session logged in as the user, during an impersonation as `$impersonatedUserId` started by the user.
     */
    private function createSession(int $userId, ?int $impersonatedUserId = null): Session
    {
        $session = new Session(storage: new ArraySessionStorage());
        $sessions = $this->actraBackend->getRepositories()->sessions();
        $authSessionId = $sessions->insert(
            parentId: null,
            userId: $userId,
            clientData: BackendPageRenderer::createClientData(),
        );
        if ($impersonatedUserId !== null) {
            $authSessionId = $sessions->insert(
                parentId: $authSessionId,
                userId: $impersonatedUserId,
                clientData: BackendPageRenderer::createClientData(),
            );
        }
        new AuthSession(session: $session)->logIn(authSessionId: $authSessionId);

        return $session;
    }

    public function testWithoutSessionDecidesNothing(): void
    {
        $this->assertNull($this->provider->findLanguageCode(httpRequest: $this->httpRequest, session: null));
        // A visitor without session cookie
        $this->assertNull($this->provider->findLanguageCode(
            httpRequest: $this->httpRequest,
            session: new Session(storage: new ArraySessionStorage(active: false)),
        ));
    }

    public function testWithoutLoginDecidesNothing(): void
    {
        $session = new Session(storage: new ArraySessionStorage());

        $this->assertNull($this->provider->findLanguageCode(httpRequest: $this->httpRequest, session: $session));
    }

    public function testLoggedInUserGetsTheProfileLanguage(): void
    {
        $session = $this->createSession(
            userId: $this->testUsers->create(email: ProfileLanguageProviderTest::email(), languageCode: 'en'),
        );

        $this->assertSame('en', $this->provider->findLanguageCode(httpRequest: $this->httpRequest, session: $session));
    }

    public function testUserWithoutLanguageDecidesNothing(): void
    {
        $session = $this->createSession(
            userId: $this->testUsers->create(email: ProfileLanguageProviderTest::email()),
        );

        $this->assertNull($this->provider->findLanguageCode(httpRequest: $this->httpRequest, session: $session));
    }

    public function testImpersonationKeepsTheLanguageOfTheImpersonatingUser(): void
    {
        $session = $this->createSession(
            userId: $this->testUsers->create(email: ProfileLanguageProviderTest::email(), languageCode: 'de'),
            impersonatedUserId: $this->testUsers->create(
                email: ProfileLanguageProviderTest::email(),
                languageCode: 'en',
            ),
        );

        $this->assertSame('de', $this->provider->findLanguageCode(httpRequest: $this->httpRequest, session: $session));
    }
}
