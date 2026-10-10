<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\core\HttpRequest;
use actra\yuf\exception\UnauthorizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `ActraBackend::authenticateBearerOrThrow()`: a valid key alone is not enough, the user must be active with a right
 * and call from the user's IP whitelist, and the API must be on.
 */
final class ApiKeyAuthenticationTest extends TestCase
{
    private const string WHITELISTED_IP = '192.0.2.50';
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->testUsers = new TestUsers(db: TestDatabase::connect());
    }

    private static function createBackend(bool $hasApi = true): ActraBackend
    {
        return ActraBackendTestInstance::create(dbSettings: TestDatabase::settings(), hasApi: $hasApi);
    }

    /**
     * @param list<string> $ipWhitelist
     *
     * @return string The API key of the new user
     */
    private function createUserWithKey(
        ActraBackend $actraBackend,
        bool $isActive = true,
        bool $withRights = true,
        array $ipWhitelist = [ApiKeyAuthenticationTest::WHITELISTED_IP],
    ): string {
        $userId = $this->testUsers->create(
            email: 'api-' . bin2hex(string: random_bytes(length: 4)) . '@example.com',
            isActive: $isActive,
            withRights: $withRights,
            ipWhitelist: $ipWhitelist,
        );

        return $actraBackend->getRepositories()->apiKeys()->createForUserId(userId: $userId);
    }

    private static function request(string $apiKey, string $ip = ApiKeyAuthenticationTest::WHITELISTED_IP): HttpRequest
    {
        return new HttpRequest(
            host: 'example.com',
            remoteAddress: $ip,
            headers: ['authorization' => 'Bearer ' . $apiKey],
        );
    }

    public function testValidKeyOfAnActiveUserFromTheWhitelist(): void
    {
        $actraBackend = ApiKeyAuthenticationTest::createBackend();
        $apiKey = $this->createUserWithKey(actraBackend: $actraBackend);

        $myAuthUser = $actraBackend->authenticateBearerOrThrow(httpRequest: ApiKeyAuthenticationTest::request(apiKey: $apiKey));

        $this->assertTrue($myAuthUser->isActive);
        $this->assertTrue($myAuthUser->canManageUsers());
    }

    /**
     * @return iterable<string, array{bool, bool, list<string>, string, bool}> active, rights, whitelist, IP, API on
     */
    public static function refusedRequests(): iterable
    {
        yield 'inactive user' => [false, true, [ApiKeyAuthenticationTest::WHITELISTED_IP], ApiKeyAuthenticationTest::WHITELISTED_IP, true];
        yield 'user without rights' => [true, false, [ApiKeyAuthenticationTest::WHITELISTED_IP], ApiKeyAuthenticationTest::WHITELISTED_IP, true];
        yield 'IP outside the whitelist' => [true, true, [ApiKeyAuthenticationTest::WHITELISTED_IP], '203.0.113.66', true];
        yield 'user without whitelist' => [true, true, [], '203.0.113.66', true];
        yield 'API turned off' => [true, true, [ApiKeyAuthenticationTest::WHITELISTED_IP], ApiKeyAuthenticationTest::WHITELISTED_IP, false];
    }

    /**
     * @param list<string> $ipWhitelist
     */
    #[DataProvider('refusedRequests')]
    public function testValidKeyIsRefused(
        bool $isActive,
        bool $withRights,
        array $ipWhitelist,
        string $ip,
        bool $hasApi,
    ): void {
        $actraBackend = ApiKeyAuthenticationTest::createBackend(hasApi: $hasApi);
        $apiKey = $this->createUserWithKey(
            actraBackend: $actraBackend,
            isActive: $isActive,
            withRights: $withRights,
            ipWhitelist: $ipWhitelist,
        );

        $this->expectException(UnauthorizedException::class);
        $actraBackend->authenticateBearerOrThrow(httpRequest: ApiKeyAuthenticationTest::request(apiKey: $apiKey, ip: $ip));
    }

    public function testWrongSecretIsRefused(): void
    {
        $actraBackend = ApiKeyAuthenticationTest::createBackend();
        $apiKey = $this->createUserWithKey(actraBackend: $actraBackend);

        $this->expectException(UnauthorizedException::class);
        $actraBackend->authenticateBearerOrThrow(
            httpRequest: ApiKeyAuthenticationTest::request(apiKey: substr(string: $apiKey, offset: 0, length: -1) . 'x'),
        );
    }

    public function testLowercasePublicIdIsRefused(): void
    {
        $actraBackend = ApiKeyAuthenticationTest::createBackend();
        // api_key_<public-id>_<secret>; a public ID of digits only has no lower case: create keys until it has a letter
        do {
            $apiKey = $this->createUserWithKey(actraBackend: $actraBackend);
            $publicId = substr(string: $apiKey, offset: 8, length: 6);
        } while (ctype_digit(text: $publicId));

        $this->expectException(UnauthorizedException::class);
        $actraBackend->authenticateBearerOrThrow(
            httpRequest: ApiKeyAuthenticationTest::request(
                apiKey: substr_replace(string: $apiKey, replace: strtolower(string: $publicId), offset: 8, length: 6),
            ),
        );
    }
}
