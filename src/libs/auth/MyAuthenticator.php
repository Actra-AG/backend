<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\Authenticator;
use actra\yuf\auth\AuthMethodEnum;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthUser;

/**
 * @internal
 */
final class MyAuthenticator extends Authenticator
{
    public private(set) ?MyAuthUser $user = null;

    public function __construct(private readonly BackendViewContext $context)
    {
        parent::__construct(
            httpRequest: $context->viewContext->httpRequest,
            authSession: $context->authSession,
            maxAllowedWrongPasswordAttempts: $context->actraBackend->actraBackendSettings->maxAllowedLoginAttempts,
        );
    }

    public function tokenLogin(string $inputToken): bool
    {
        $dbAuthToken = new AuthTokens(context: $this->context)->claim(
            type: AuthTokenTypeEnum::LOGIN,
            inputToken: $inputToken,
        );
        if ($dbAuthToken === null) {
            return false;
        }
        return $this->doLogin(
            authMethod: AuthMethodEnum::OTP,
            userName: $dbAuthToken->email,
            passwordToCheck: null,
        );
    }

    #[\Override]
    protected function checkLoginCredentials(AuthUser $authUser): bool
    {
        return true;
    }

    #[\Override]
    protected function createAuthUserByUserName(string $userName): ?MyAuthUser
    {
        $dbAuthUser = $this->context->repositories->users()->selectByEmail(email: $userName);
        if ($dbAuthUser === null) {
            return null;
        }
        $this->user = new MyAuthUser(
            dbAuthUser: $dbAuthUser,
            parentSessionId: null,
            repositories: $this->context->repositories,
            clientData: $this->context->clientData,
        );
        return $this->user;
    }

    #[\Override]
    public function logAuthResult(
        ?int $userId,
        string $sessionId,
        string $ip,
        string $userName,
        AuthResultEnum $authResult,
    ): void {
        $this->context->repositories->logins()->insert(
            userId: $userId,
            sessionId: $sessionId,
            ipAddress: $ip,
            inputEmail: $userName,
            authResult: $authResult,
        );
    }
}
