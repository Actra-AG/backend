<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\view;

use actra\backend\settings\ApiKeyIpPolicyEnum;
use actra\backend\tests\Double\BackendPageRenderer;
use actra\backend\tests\Double\ResponseSentException;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * API keys for users without IP whitelist (`ActraBackendSettings::$apiKeyIpPolicy`).
 */
final class ApiKeyIpPolicyTest extends TestCase
{
    public function testAUserWithoutWhitelistGetsNoKeyByDefault(): void
    {
        $renderer = $this->createRenderer(apiKeyIpPolicy: ApiKeyIpPolicyEnum::WHITELIST_REQUIRED);
        $userId = $this->createUser(ipWhitelist: []);

        $html = $this->render(renderer: $renderer, fileTitle: 'user', userId: $userId);

        $this->assertStringNotContainsString('userGenerateApiKey-' . $userId . '.html', $html);
        $this->assertStringContainsString('erst generiert werden, wenn eine IP-Whitelist hinterlegt ist', $html);
        $this->expectException(NotFoundException::class);
        $this->render(renderer: $renderer, fileTitle: 'userGenerateApiKey', userId: $userId);
    }

    public function testAUserWithoutWhitelistGetsAKeyWithOptionalWhitelist(): void
    {
        $renderer = $this->createRenderer(apiKeyIpPolicy: ApiKeyIpPolicyEnum::WHITELIST_OPTIONAL);
        $userId = $this->createUser(ipWhitelist: []);

        $html = $this->render(renderer: $renderer, fileTitle: 'user', userId: $userId);
        $this->assertStringContainsString('userGenerateApiKey-' . $userId . '.html', $html);
        $html = $this->render(renderer: $renderer, fileTitle: 'userMod', userId: $userId);
        $this->assertStringContainsString('Ohne IP-Whitelist funktioniert der API-Key von jeder IP-Adresse aus.', $html);
        $html = $this->render(renderer: $renderer, fileTitle: 'userGenerateApiKey', userId: $userId);
        $this->assertStringContainsString('<form', $html);
    }

    public function testTheWhitelistOfAUserWithKeyCanBeEmptiedOnlyWithOptionalWhitelist(): void
    {
        foreach ([ApiKeyIpPolicyEnum::WHITELIST_REQUIRED, ApiKeyIpPolicyEnum::WHITELIST_OPTIONAL] as $policy) {
            $renderer = $this->createRenderer(apiKeyIpPolicy: $policy);
            $userId = $this->createUser(ipWhitelist: ['192.0.2.1']);
            $renderer->actraBackend->getRepositories()->apiKeys()->createForUserId(userId: $userId);
            $dbAuthUser = $renderer->actraBackend->getRepositories()->users()->selectById(id: $userId)
                ?? self::fail('No user');

            $this->render(
                renderer: $renderer,
                fileTitle: 'userMod',
                userId: $userId,
                formName: 'UserModForm-' . $userId,
                postParameters: [
                    'firstName' => $dbAuthUser->firstName,
                    'lastName' => $dbAuthUser->lastName,
                    'email' => $dbAuthUser->email,
                    'phone' => '',
                    'active' => ['checked'],
                    'userGroups' => ['1'],
                    'ipWhitelistField' => '',
                    'save' => '',
                ],
            );

            $ipWhitelist = $renderer->actraBackend->getRepositories()->users()->selectById(id: $userId)?->ipWhitelist;
            $this->assertSame(
                $policy === ApiKeyIpPolicyEnum::WHITELIST_OPTIONAL ? [] : ['192.0.2.1'],
                $ipWhitelist,
                $policy->name,
            );
        }
    }

    private function createRenderer(ApiKeyIpPolicyEnum $apiKeyIpPolicy): BackendPageRenderer
    {
        return new BackendPageRenderer(dbSettings: TestDatabase::settings(), apiKeyIpPolicy: $apiKeyIpPolicy);
    }

    /**
     * @param list<string> $ipWhitelist
     */
    private function createUser(array $ipWhitelist): int
    {
        return new TestUsers(db: TestDatabase::connect())->create(
            email: 'api-policy-' . bin2hex(string: random_bytes(length: 4)) . '@example.com',
            ipWhitelist: $ipWhitelist,
        );
    }

    /**
     * @param array<string, string|list<string>> $postParameters
     */
    private function render(
        BackendPageRenderer $renderer,
        string $fileTitle,
        int $userId,
        ?string $formName = null,
        array $postParameters = [],
    ): string {
        $session = new Session(storage: new ArraySessionStorage());
        $actorId = $this->createUser(ipWhitelist: [BackendPageRenderer::IP_ADDRESS]);
        $renderer->logIn(session: $session, userId: $actorId);
        $csrfToken = ViewContextFactory::csrfToken(session: $session);
        try {
            return $renderer->render(
                languageCode: 'de',
                fileTitle: $fileTitle,
                pathVars: [(string) $userId],
                session: $session,
                postParameters: $postParameters === []
                    ? []
                    : [CsrfTokenSource::FIELD_NAME => $csrfToken, ...$postParameters],
                formName: $formName,
            );
        } catch (ResponseSentException) {
            // A saved form redirects
            return '';
        }
    }
}
