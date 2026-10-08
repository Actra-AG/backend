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
        DbAuthUser $dbAuthUser,
        bool $usedPasswordLogin,
    ): void {
        $token = DbAuthTokenRepository::createToken(
            dbAuthUser: $dbAuthUser,
            authTokenTypeEnum: $this,
        );
        $_SESSION['auth_token_' . $this->value] = $token;
        $_SESSION['failed_attempts_' . $this->value] = 0;
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

    public function claim(string $inputToken): ?DbAuthToken
    {
        if ($this->getFailedAttempts() > ActraBackend::get()->actraBackendSettings->maxAllowedLoginAttempts) {
            return null;
        }
        if (
            !array_key_exists(
                key: 'auth_token_' . $this->value,
                array: $_SESSION,
            )
            || $_SESSION['auth_token_' . $this->value] !== $inputToken
        ) {
            $this->increaseFailedAttempts();
            return null;
        }
        unset($_SESSION['auth_token_' . $this->value]);
        $dbAuthToken = DbAuthTokenRepository::getClaimable(
            authTokenType: AuthTokenTypeEnum::LOGIN,
            token: $inputToken,
        );
        if ($dbAuthToken === null) {
            return null;
        }
        DbAuthTokenRepository::claim(dbAuthToken: $dbAuthToken);
        return $dbAuthToken;
    }

    private function getFailedAttempts(): int
    {
        return array_key_exists(
            key: 'failed_attempts_' . $this->value,
            array: $_SESSION,
        ) ? $_SESSION['failed_attempts_' . $this->value] : 0;
    }

    private function increaseFailedAttempts(): void
    {
        if (!array_key_exists(key: 'failed_attempts_' . $this->value, array: $_SESSION)) {
            $_SESSION['failed_attempts_' . $this->value] = 0;
        }
        $_SESSION['failed_attempts_' . $this->value]++;
    }
}
