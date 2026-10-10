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
use actra\yuf\auth\SecretTokenHash;
use actra\yuf\core\Route;
use PHPUnit\Framework\TestCase;

/**
 * The session part of claiming a token (the database part runs only for a matching token below the attempt limit).
 * The session keeps only the hash of the token and the user it was sent to.
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

    private function keepToken(AuthTokenTypeEnum $type, string $token): void
    {
        $this->context->session->set(
            key: 'auth_token_' . $type->value,
            value: SecretTokenHash::fromSecret(secret: $token)->hash,
        );
        $this->context->session->set(key: 'auth_token_user_' . $type->value, value: 1);
    }

    private function claim(AuthTokenTypeEnum $type, string $inputToken): void
    {
        $this->assertNull(new AuthTokens(context: $this->context)->claim(type: $type, inputToken: $inputToken));
    }

    public function testWrongTokenCountsAFailedAttempt(): void
    {
        $session = $this->context->session;
        $this->keepToken(type: AuthTokenTypeEnum::LOGIN, token: '123456');

        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '654321');
        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '654321');

        $this->assertSame(2, $session->getInt(key: 'failed_attempts_login'));
        $this->assertSame(
            SecretTokenHash::fromSecret(secret: '123456')->hash,
            $session->getString(key: 'auth_token_login'),
        );
    }

    public function testTokenOfAnotherTypeDoesNotMatch(): void
    {
        $this->keepToken(type: AuthTokenTypeEnum::PASSWORD, token: '123456');

        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '123456');

        $this->assertSame(1, $this->context->session->getInt(key: 'failed_attempts_login'));
    }

    public function testTooManyFailedAttemptsBlockTheCorrectToken(): void
    {
        $maxAttempts = $this->context->actraBackend->actraBackendSettings->maxAllowedLoginAttempts;
        $this->keepToken(type: AuthTokenTypeEnum::LOGIN, token: '123456');
        $this->context->session->set(key: 'failed_attempts_login', value: $maxAttempts);

        $this->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: '123456');

        $this->assertSame(
            SecretTokenHash::fromSecret(secret: '123456')->hash,
            $this->context->session->getString(key: 'auth_token_login'),
        );
    }
}
