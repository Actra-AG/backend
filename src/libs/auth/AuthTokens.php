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
use actra\yuf\auth\SecretTokenHash;
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
        $tokens = $context->repositories->tokens();
        $tokenSendLimit = $context->actraBackend->actraBackendSettings->tokenSendLimit;
        $token = $tokenSendLimit === null
            ? $tokens->createToken(dbAuthUser: $dbAuthUser, authTokenTypeEnum: $type, clientData: $context->clientData)
            : $tokens->createTokenWithinLimit(
                dbAuthUser: $dbAuthUser,
                authTokenTypeEnum: $type,
                clientData: $context->clientData,
                tokenSendLimit: $tokenSendLimit,
            );
        if ($token === null) {
            return;
        }
        // The session keeps only the hash of the token and the user it was sent to
        $context->session->set(
            key: AuthTokens::getTokenKey(type: $type),
            value: SecretTokenHash::fromSecret(secret: $token)->hash,
        );
        $context->session->set(key: AuthTokens::getUserIdKey(type: $type), value: $dbAuthUser->id);
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

    /**
     * The token if it is the one kept in the session and still claimable, `null` otherwise (counted as failed attempt).
     */
    public function claim(AuthTokenTypeEnum $type, #[\SensitiveParameter] string $inputToken): ?DbAuthToken
    {
        $session = $this->context->session;
        $failedAttempts = $session->getInt(key: AuthTokens::getFailedAttemptsKey(type: $type)) ?? 0;
        if ($failedAttempts >= $this->context->actraBackend->actraBackendSettings->maxAllowedLoginAttempts) {
            return null;
        }
        $tokenHash = SecretTokenHash::tryFrom(hash: $session->getString(key: AuthTokens::getTokenKey(type: $type)) ?? '');
        $userId = $session->getInt(key: AuthTokens::getUserIdKey(type: $type));
        if ($tokenHash === null || $userId === null || $inputToken === '' || !$tokenHash->isValid(secret: $inputToken)) {
            $session->set(key: AuthTokens::getFailedAttemptsKey(type: $type), value: $failedAttempts + 1);

            return null;
        }
        $session->remove(key: AuthTokens::getTokenKey(type: $type));
        $session->remove(key: AuthTokens::getUserIdKey(type: $type));
        $tokens = $this->context->repositories->tokens();
        $dbAuthToken = $tokens->getClaimable(authTokenType: $type, token: $inputToken, userId: $userId);
        if (
            $dbAuthToken === null
            || !$tokens->claim(dbAuthToken: $dbAuthToken, clientData: $this->context->clientData)
        ) {
            return null;
        }

        return $dbAuthToken;
    }

    private static function getTokenKey(AuthTokenTypeEnum $type): string
    {
        return 'auth_token_' . $type->value;
    }

    private static function getUserIdKey(AuthTokenTypeEnum $type): string
    {
        return 'auth_token_user_' . $type->value;
    }

    private static function getFailedAttemptsKey(AuthTokenTypeEnum $type): string
    {
        return 'failed_attempts_' . $type->value;
    }
}
