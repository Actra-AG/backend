<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthUser;
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
            loginRateLimit: $context->actraBackend->actraBackendSettings->loginRateLimit,
        );
    }

    /**
     * Logs in with a claimed login token; the logged-in user, `null` for a wrong token or a rejected user. Every attempt
     * is counted per client IP address before the token is checked (yuf's `consumeCodeAttempt()`, `codeLimits` of the
     * `LoginRateLimit`): above the limit the token is not checked, `$authResult` is `ERROR_RATE_LIMITED`. A wrong token
     * is logged as `ERROR_WRONG_CODE` with the user the session's token was sent to (the token itself never). The
     * login itself is not counted again (yuf's `logInVerifiedUser()`): the token was sent after a counted step.
     */
    public function tokenLogin(#[\SensitiveParameter] string $inputToken): ?MyAuthUser
    {
        $authTokens = new AuthTokens(context: $this->context);
        // The user of the log; the code step does not know the address, so a session without token logs none
        $pendingUserId = $authTokens->findPendingUserId(type: AuthTokenTypeEnum::LOGIN);
        $pendingUserName = $pendingUserId === null
            ? ''
            : $this->context->repositories->users()->selectById(id: $pendingUserId)->email ?? '';
        if (!$this->consumeCodeAttempt(userName: $pendingUserName, userId: $pendingUserId)) {
            return null;
        }
        $dbAuthToken = $authTokens->claim(type: AuthTokenTypeEnum::LOGIN, inputToken: $inputToken);
        if ($dbAuthToken === null) {
            $this->rejectWrongCode(userName: $pendingUserName, userId: $pendingUserId);

            return null;
        }
        $dbAuthUser = $this->context->repositories->users()->selectById(id: $dbAuthToken->userId);
        if ($dbAuthUser === null) {
            return null;
        }
        $isLoggedIn = $this->logInVerifiedUser(
            authMethod: AuthMethodEnum::OTP,
            authUser: $this->createMyAuthUser(dbAuthUser: $dbAuthUser),
            userName: $dbAuthToken->email,
        );

        return $isLoggedIn ? MyAuthUser::findLoggedIn(
            authSession: $this->context->authSession,
            repositories: $this->context->repositories,
            clientData: $this->context->clientData,
        ) : null;
    }

    /**
     * Logs in the user of a redeemed login ticket (yuf's `LoginHandOver`, `AuthMethodEnum::SSO`): the subject is the
     * user ID, the same on the login host and on the site. The logged-in user, `null` for an unknown or rejected user
     * (logged with the email address of the user, else with the subject; the checks of yuf's `logInVerifiedUser()`
     * apply: IP whitelist, inactive, lock-out).
     */
    public function handOverLogin(string $subject): ?MyAuthUser
    {
        $dbAuthUser = preg_match(pattern: '/^[1-9][0-9]{0,18}$/D', subject: $subject) === 1
            ? $this->context->repositories->users()->selectById(id: (int) $subject)
            : null;
        $isLoggedIn = $this->logInIdentifiedUser(
            authMethod: AuthMethodEnum::SSO,
            authUser: $dbAuthUser === null ? null : $this->createMyAuthUser(dbAuthUser: $dbAuthUser),
            identifier: $dbAuthUser === null ? $subject : $dbAuthUser->email,
        );

        return $isLoggedIn ? MyAuthUser::findLoggedIn(
            authSession: $this->context->authSession,
            repositories: $this->context->repositories,
            clientData: $this->context->clientData,
        ) : null;
    }

    /**
     * The user that may get a login token by email: yuf's `precheck()` (unknown user, IP whitelist, inactive, out tried)
     * and no password (a user with password logs in with it, logged as `ERROR_NO_PASSWORD`; without password login,
     * `ActraBackendSettings::$passwordLogin`, the user has none here). Rejections are logged; `$authResult` is
     * `ERROR_RATE_LIMITED` if the rate limit blocked the request.
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

        return $this->createMyAuthUser(dbAuthUser: $dbAuthUser);
    }

    /**
     * Every user of a login step: without password login (`ActraBackendSettings::$passwordLogin`) without its stored
     * password and wrong password attempts (`MyAuthUser`).
     */
    private function createMyAuthUser(DbAuthUser $dbAuthUser): MyAuthUser
    {
        return new MyAuthUser(
            dbAuthUser: $dbAuthUser,
            parentSessionId: null,
            repositories: $this->context->repositories,
            clientData: $this->context->clientData,
            passwordLogin: $this->context->actraBackend->actraBackendSettings->passwordLogin,
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
