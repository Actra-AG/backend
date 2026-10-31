<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

use actra\backend\settings\AuthTokenTypeEnum;

/**
 * Texts of the visit, token and sent email logs.
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class LogMessages
{
    use OverridableMessages;

    public function __construct(
        public string $visitsNavigationTitle = 'Login attempts',
        public string $visitsPageTitle = 'Visits',
        public string $tokensNavigationTitle = 'Codes',
        public string $tokensPageTitle = 'Codes',
        public string $statusLabel = 'Status',
        public string $typeLabel = 'Type',
        public string $visitDateColumn = 'Date',
        public string $visitSessionIdColumn = 'Session ID',
        public string $visitIpAddressColumn = 'IP address',
        public string $visitEmailColumn = 'Email address',
        public string $tokenCreatedDateColumn = 'Created (date)',
        public string $tokenCreatedClientColumn = 'Created (client)',
        public string $tokenClaimedDateColumn = 'Redeemed (date)',
        public string $tokenClaimedClientColumn = 'Redeemed (client)',
        public string $authTokenTypePassword = 'Password reset',
        public string $authTokenTypeActivation = 'Activation',
        public string $authTokenTypeLogin = 'Login',
        public string $sentEmailsNavigationTitle = 'Sent emails',
        public string $sentEmailsPageTitle = 'Sent emails',
        public string $sentEmailDateColumn = 'Sent',
        public string $sentEmailFromColumn = 'From',
        public string $sentEmailToColumn = 'To',
        public string $sentEmailCcColumn = 'Cc',
        public string $sentEmailBccColumn = 'Bcc',
        public string $sentEmailSubjectColumn = 'Subject',
    ) {}

    public static function german(): LogMessages
    {
        return new LogMessages(
            visitsNavigationTitle: 'Anmeldeversuche',
            visitsPageTitle: 'Besuche',
            tokensNavigationTitle: 'Codes',
            tokensPageTitle: 'Codes',
            statusLabel: 'Status',
            typeLabel: 'Typ',
            visitDateColumn: 'Datum',
            visitSessionIdColumn: 'SessionID',
            visitIpAddressColumn: 'IP-Adresse',
            visitEmailColumn: 'E-Mail-Adresse',
            tokenCreatedDateColumn: 'Erstellt (Datum)',
            tokenCreatedClientColumn: 'Erstellt (Client)',
            tokenClaimedDateColumn: 'Eingelöst (Datum)',
            tokenClaimedClientColumn: 'Eingelöst (Client)',
            authTokenTypePassword: 'Passwort-Reset',
            authTokenTypeActivation: 'Aktivierung',
            authTokenTypeLogin: 'Anmeldung',
            sentEmailsNavigationTitle: 'E-Mails',
            sentEmailsPageTitle: 'Gesendete E-Mails',
            sentEmailDateColumn: 'Gesendet',
            sentEmailFromColumn: 'Von',
            sentEmailToColumn: 'An',
            sentEmailCcColumn: 'Cc',
            sentEmailBccColumn: 'Bcc',
            sentEmailSubjectColumn: 'Betreff',
        );
    }

    public function authTokenType(AuthTokenTypeEnum $authTokenType): string
    {
        return match ($authTokenType) {
            AuthTokenTypeEnum::PASSWORD => $this->authTokenTypePassword,
            AuthTokenTypeEnum::ACTIVATION => $this->authTokenTypeActivation,
            AuthTokenTypeEnum::LOGIN => $this->authTokenTypeLogin,
        };
    }
}
