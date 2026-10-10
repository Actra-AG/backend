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
    public function __construct(private readonly BackendViewContext $context)
    {
        parent::__construct(
            httpRequest: $context->viewContext->httpRequest,
            authSession: $context->authSession,
            maxAllowedWrongPasswordAttempts: $context->actraBackend->actraBackendSettings->maxAllowedLoginAttempts,
        );
    }

    /**
     * Logs in with a claimed login token; the logged-in user, `null` for a wrong token or a rejected user.
     */
    public function tokenLogin(string $inputToken): ?MyAuthUser
    {
        $dbAuthToken = new AuthTokens(context: $this->context)->claim(
            type: AuthTokenTypeEnum::LOGIN,
            inputToken: $inputToken,
        );
        if ($dbAuthToken === null) {
            return null;
        }
        $isLoggedIn = $this->doLogin(
            authMethod: AuthMethodEnum::OTP,
            userName: $dbAuthToken->email,
            passwordToCheck: null,
        );

        return $isLoggedIn ? MyAuthUser::findLoggedIn(
            authSession: $this->context->authSession,
            repositories: $this->context->repositories,
            clientData: $this->context->clientData,
        ) : null;
    }

    /**
     * The user that may get a login token by email: yuf's `precheck()` (unknown user, IP whitelist, inactive, out tried)
     * and no password (a user with password logs in with it, logged as `ERROR_NO_PASSWORD`). Rejections are logged.
     */
    public function findTokenLoginUser(string $email): ?MyAuthUser
    {
        $result = $this->precheck(userName: $email);
        if (!$result instanceof MyAuthUser) {
            return null;
        }
        if ($result->password !== null) {
            $this->logAuthResult(
                userId: $result->id,
                sessionId: $this->context->authSession->getSessionId(),
                ip: $this->context->viewContext->httpRequest->getRemoteAddress(),
                userName: $email,
                authResult: AuthResultEnum::ERROR_NO_PASSWORD,
            );

            return null;
        }

        return $result;
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
        return new MyAuthUser(
            dbAuthUser: $dbAuthUser,
            parentSessionId: null,
            repositories: $this->context->repositories,
            clientData: $this->context->clientData,
        );
    }

    #[\Override]
    protected function logAuthResult(
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
