<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\i18n\LogMessages;

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
     */
    public function render(LogMessages $messages): string
    {
        return $messages->authTokenType(authTokenType: $this);
    }
}
