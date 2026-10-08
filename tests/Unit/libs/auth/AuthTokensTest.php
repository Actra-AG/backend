<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\libs\auth\AuthTokens;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\Route;
use PHPUnit\Framework\TestCase;

/**
 * The session part of claiming a token (the database part runs only for a matching token below the attempt limit).
 */
final class AuthTokensTest extends TestCase
{
    private BackendViewContext $context;

    #[\Override]
    protected function setUp(): void
    {
        $this->context = ActraBackendTestInstance::create()->createContext(
            viewContext: ViewContextFactory::create(
                route: new Route(path: '/backend/', viewDirectory: __DIR__),
                fileTitle: 'loginToken',
            ),
        );
    }

    private function claim(AuthTokenTypeEnum $type, string $inputToken): void
    {
        $this->assertNull(new AuthTokens(context: $this->context)->claim(type: $type, inputToken: $inputToken));
    }

    public function testWrongTokenCountsAFailedAttempt(): void
    {
        $session = $this->context->session;
        $session->set(key: 'auth_token_login', value: '123456');

        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '654321');
        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '654321');

        $this->assertSame(2, $session->getInt(key: 'failed_attempts_login'));
        $this->assertSame('123456', $session->getString(key: 'auth_token_login'));
    }

    public function testTokenOfAnotherTypeDoesNotMatch(): void
    {
        $this->context->session->set(key: 'auth_token_password', value: '123456');

        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '123456');

        $this->assertSame(1, $this->context->session->getInt(key: 'failed_attempts_login'));
    }

    public function testTooManyFailedAttemptsBlockTheCorrectToken(): void
    {
        $maxAttempts = $this->context->actraBackend->actraBackendSettings->maxAllowedLoginAttempts;
        $this->context->session->set(key: 'auth_token_login', value: '123456');
        $this->context->session->set(key: 'failed_attempts_login', value: $maxAttempts + 1);

        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '123456');

        $this->assertSame('123456', $this->context->session->getString(key: 'auth_token_login'));
    }
}
