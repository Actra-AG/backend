<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\html\HtmlText;

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

    public function render(): string
    {
        return (match ($this) {
            AuthTokenTypeEnum::PASSWORD => HtmlText::encoded(textContent: 'Passwort-Reset'),
            AuthTokenTypeEnum::ACTIVATION => HtmlText::encoded(textContent: 'Aktivierung'),
            AuthTokenTypeEnum::LOGIN => HtmlText::encoded(textContent: 'Anmeldung'),
        })->render();
    }
}