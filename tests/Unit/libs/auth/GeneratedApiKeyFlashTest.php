<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\libs\auth\GeneratedApiKeyFlash;
use PHPUnit\Framework\TestCase;

final class GeneratedApiKeyFlashTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testPullReturnsTheStoredKeyOnlyOnce(): void
    {
        GeneratedApiKeyFlash::store(userID: 5, apiKey: 'api_key_public_secret');

        $this->assertSame('api_key_public_secret', GeneratedApiKeyFlash::pull(userID: 5));
        $this->assertNull(GeneratedApiKeyFlash::pull(userID: 5));
    }

    public function testPullForAnotherUserKeepsTheKey(): void
    {
        GeneratedApiKeyFlash::store(userID: 5, apiKey: 'api_key_public_secret');

        $this->assertNull(GeneratedApiKeyFlash::pull(userID: 6));
        $this->assertSame('api_key_public_secret', GeneratedApiKeyFlash::pull(userID: 5));
    }

    public function testStoreReplacesThePreviousKey(): void
    {
        GeneratedApiKeyFlash::store(userID: 5, apiKey: 'first');
        GeneratedApiKeyFlash::store(userID: 5, apiKey: 'second');

        $this->assertSame('second', GeneratedApiKeyFlash::pull(userID: 5));
    }

    public function testPullWithoutStoredKeyReturnsNull(): void
    {
        $this->assertNull(GeneratedApiKeyFlash::pull(userID: 5));
    }

    public function testPullIgnoresInvalidSessionData(): void
    {
        $_SESSION['actra_backend_generated_api_key'] = ['userID' => '5', 'apiKey' => 'api_key_public_secret'];

        $this->assertNull(GeneratedApiKeyFlash::pull(userID: 5));
    }
}
