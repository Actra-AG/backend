<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\settings;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;

/**
 * The session part of claiming a token (the database part runs only for a matching token below the attempt limit).
 */
final class AuthTokenTypeEnumTest extends TestCase
{
    private Session $session;

    #[\Override]
    protected function setUp(): void
    {
        // claim() reads the attempt limit from the settings of the backend
        ActraBackendTestInstance::get();
        $this->session = new Session(storage: new ArraySessionStorage());
    }

    public function testWrongTokenCountsAFailedAttempt(): void
    {
        $this->session->set(key: 'auth_token_login', value: '123456');

        $this->assertNull(AuthTokenTypeEnum::LOGIN->claim(session: $this->session, inputToken: '654321'));
        $this->assertNull(AuthTokenTypeEnum::LOGIN->claim(session: $this->session, inputToken: '654321'));

        $this->assertSame(2, $this->session->getInt(key: 'failed_attempts_login'));
        $this->assertSame('123456', $this->session->getString(key: 'auth_token_login'));
    }

    public function testTokenOfAnotherTypeDoesNotMatch(): void
    {
        $this->session->set(key: 'auth_token_password', value: '123456');

        $this->assertNull(AuthTokenTypeEnum::LOGIN->claim(session: $this->session, inputToken: '123456'));
        $this->assertSame(1, $this->session->getInt(key: 'failed_attempts_login'));
    }

    public function testTooManyFailedAttemptsBlockTheCorrectToken(): void
    {
        $maxAttempts = ActraBackendTestInstance::get()->actraBackendSettings->maxAllowedLoginAttempts;
        $this->session->set(key: 'auth_token_login', value: '123456');
        $this->session->set(key: 'failed_attempts_login', value: $maxAttempts + 1);

        $this->assertNull(AuthTokenTypeEnum::LOGIN->claim(session: $this->session, inputToken: '123456'));
        $this->assertSame('123456', $this->session->getString(key: 'auth_token_login'));
    }
}
