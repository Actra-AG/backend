<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthToken;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\email\EmailLoginToken;
use actra\backend\libs\email\EmailPasswordResetLink;
use actra\backend\settings\AuthTokenTypeEnum;
use LogicException;

/**
 * Creates the one-time tokens of the backend (login code, password reset link), keeps the expected token in the
 * session and claims it.
 *
 * @internal
 */
final readonly class AuthTokens
{
    public function __construct(private BackendViewContext $context) {}

    /**
     * Creates the token, keeps it in the session and mails it. Above the `TokenSendLimit` of the settings it does
     * nothing: the session keeps the token sent before, which stays valid until it expires.
     */
    public function createAndSend(AuthTokenTypeEnum $type, DbAuthUser $dbAuthUser, bool $usedPasswordLogin): void
    {
        $context = $this->context;
        if ($this->isSendLimitReached(type: $type, dbAuthUser: $dbAuthUser)) {
            return;
        }
        $token = $context->repositories->tokens()->createToken(
            dbAuthUser: $dbAuthUser,
            authTokenTypeEnum: $type,
            clientData: $context->clientData,
        );
        $context->session->set(key: AuthTokens::getTokenKey(type: $type), value: $token);
        $context->session->set(key: AuthTokens::getFailedAttemptsKey(type: $type), value: 0);
        match ($type) {
            AuthTokenTypeEnum::LOGIN => EmailLoginToken::send(
                context: $context,
                dbAuthUser: $dbAuthUser,
                token: $token,
                expirationInMinutes: $type->getExpirationInMinutes(),
                usedPasswordLogin: $usedPasswordLogin,
            ),
            AuthTokenTypeEnum::PASSWORD => EmailPasswordResetLink::send(
                context: $context,
                dbAuthUser: $dbAuthUser,
                token: $token,
                expirationInMinutes: $type->getExpirationInMinutes(),
            ),
            AuthTokenTypeEnum::ACTIVATION => throw new LogicException(
                message: 'The backend sends no activation tokens; projects that use AuthTokenTypeEnum::ACTIVATION '
                    . 'send them.',
            ),
        };
    }

    private function isSendLimitReached(AuthTokenTypeEnum $type, DbAuthUser $dbAuthUser): bool
    {
        $tokenSendLimit = $this->context->actraBackend->actraBackendSettings->tokenSendLimit;
        if ($tokenSendLimit === null) {
            return false;
        }

        return $this->context->repositories->tokens()->countRegisteredWithin(
            userId: $dbAuthUser->id,
            authTokenType: $type,
            minutes: $tokenSendLimit->withinMinutes,
        ) >= $tokenSendLimit->maxTokens;
    }

    /**
     * The token if it is the one kept in the session and still claimable, `null` otherwise (counted as failed attempt).
     */
    public function claim(AuthTokenTypeEnum $type, string $inputToken): ?DbAuthToken
    {
        $session = $this->context->session;
        $failedAttempts = $session->getInt(key: AuthTokens::getFailedAttemptsKey(type: $type)) ?? 0;
        if ($failedAttempts > $this->context->actraBackend->actraBackendSettings->maxAllowedLoginAttempts) {
            return null;
        }
        if ($session->getString(key: AuthTokens::getTokenKey(type: $type)) !== $inputToken) {
            $session->set(key: AuthTokens::getFailedAttemptsKey(type: $type), value: $failedAttempts + 1);

            return null;
        }
        $session->remove(key: AuthTokens::getTokenKey(type: $type));
        $tokens = $this->context->repositories->tokens();
        $dbAuthToken = $tokens->getClaimable(authTokenType: $type, token: $inputToken);
        if ($dbAuthToken === null) {
            return null;
        }
        $tokens->claim(dbAuthToken: $dbAuthToken, clientData: $this->context->clientData);

        return $dbAuthToken;
    }

    private static function getTokenKey(AuthTokenTypeEnum $type): string
    {
        return 'auth_token_' . $type->value;
    }

    private static function getFailedAttemptsKey(AuthTokenTypeEnum $type): string
    {
        return 'failed_attempts_' . $type->value;
    }
}
