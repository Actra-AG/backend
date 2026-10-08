<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\libs\auth\GeneratedApiKeyFlash;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

final class GeneratedApiKeyFlashTest extends TestCase
{
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    public function testPullReturnsTheStoredKeyOnlyOnce(): void
    {
        GeneratedApiKeyFlash::store(session: $this->session, userID: 5, apiKey: 'api_key_public_secret');

        $this->assertSame('api_key_public_secret', GeneratedApiKeyFlash::pull(session: $this->session, userID: 5));
        $this->assertNull(GeneratedApiKeyFlash::pull(session: $this->session, userID: 5));
    }

    public function testPullForAnotherUserKeepsTheKey(): void
    {
        GeneratedApiKeyFlash::store(session: $this->session, userID: 5, apiKey: 'api_key_public_secret');

        $this->assertNull(GeneratedApiKeyFlash::pull(session: $this->session, userID: 6));
        $this->assertSame('api_key_public_secret', GeneratedApiKeyFlash::pull(session: $this->session, userID: 5));
    }

    public function testStoreReplacesThePreviousKey(): void
    {
        GeneratedApiKeyFlash::store(session: $this->session, userID: 5, apiKey: 'first');
        GeneratedApiKeyFlash::store(session: $this->session, userID: 5, apiKey: 'second');

        $this->assertSame('second', GeneratedApiKeyFlash::pull(session: $this->session, userID: 5));
    }

    public function testPullWithoutStoredKeyReturnsNull(): void
    {
        $this->assertNull(GeneratedApiKeyFlash::pull(session: $this->session, userID: 5));
    }

    public function testPullIgnoresInvalidSessionData(): void
    {
        $this->session->set(
            key: 'actra_backend_generated_api_key',
            value: ['userID' => '5', 'apiKey' => 'api_key_public_secret'],
        );

        $this->assertNull(GeneratedApiKeyFlash::pull(session: $this->session, userID: 5));
    }
}
