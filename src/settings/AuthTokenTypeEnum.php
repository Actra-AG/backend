<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\ActraBackend;
use actra\backend\i18n\LogMessages;
use actra\backend\libs\db\DbAuthToken;
use actra\backend\libs\db\DbAuthTokenRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\email\EmailLoginToken;
use actra\backend\libs\email\EmailPasswordResetLink;
use actra\yuf\session\Session;
use Exception;

enum AuthTokenTypeEnum: string
{
    case PASSWORD = 'password';
    case ACTIVATION = 'activation';
    case LOGIN = 'login';

    public function getExpirationInMinutes(): int
    {
        return match ($this) {
            AuthTokenTypeEnum::PASSWORD, AuthTokenTypeEnum::ACTIVATION, AuthTokenTypeEnum::LOGIN => 15,
        };
    }

    /**
     * Plain text label (not encoded).
     *
     * @param ?LogMessages $messages Default: the messages of the backend
     */
    public function render(?LogMessages $messages = null): string
    {
        return ($messages ?? ActraBackend::messages()->log)->authTokenType(authTokenType: $this);
    }

    public function createAndSend(
        Session $session,
        DbAuthUser $dbAuthUser,
        bool $usedPasswordLogin,
    ): void {
        $token = DbAuthTokenRepository::createToken(
            dbAuthUser: $dbAuthUser,
            authTokenTypeEnum: $this,
        );
        $session->set(key: $this->getTokenKey(), value: $token);
        $session->set(key: $this->getFailedAttemptsKey(), value: 0);
        match ($this) {
            AuthTokenTypeEnum::LOGIN => EmailLoginToken::send(
                dbAuthUser: $dbAuthUser,
                token: $token,
                expirationInMinutes: $this->getExpirationInMinutes(),
                usedPasswordLogin: $usedPasswordLogin,
            ),
            AuthTokenTypeEnum::PASSWORD => EmailPasswordResetLink::send(
                dbAuthUser: $dbAuthUser,
                token: $token,
                expirationInMinutes: $this->getExpirationInMinutes(),
            ),
            AuthTokenTypeEnum::ACTIVATION => throw new Exception(message: 'To be implemented'),
        };
    }

    public function claim(Session $session, string $inputToken): ?DbAuthToken
    {
        $failedAttempts = $session->getInt(key: $this->getFailedAttemptsKey()) ?? 0;
        if ($failedAttempts > ActraBackend::get()->actraBackendSettings->maxAllowedLoginAttempts) {
            return null;
        }
        if ($session->getString(key: $this->getTokenKey()) !== $inputToken) {
            $session->set(key: $this->getFailedAttemptsKey(), value: $failedAttempts + 1);
            return null;
        }
        $session->remove(key: $this->getTokenKey());
        $dbAuthToken = DbAuthTokenRepository::getClaimable(
            authTokenType: $this,
            token: $inputToken,
        );
        if ($dbAuthToken === null) {
            return null;
        }
        DbAuthTokenRepository::claim(dbAuthToken: $dbAuthToken);
        return $dbAuthToken;
    }

    private function getTokenKey(): string
    {
        return 'auth_token_' . $this->value;
    }

    private function getFailedAttemptsKey(): string
    {
        return 'failed_attempts_' . $this->value;
    }
}
