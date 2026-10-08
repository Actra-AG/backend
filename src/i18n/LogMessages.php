<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\AuthResultEnum;

/**
 * Texts of the visit and token logs.
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
        public string $filterNoAccess = 'No access',
        public string $filterUnconfirmedAccess = 'Unconfirmed access',
        public string $visitDateColumn = 'Date',
        public string $visitSessionIdColumn = 'Session ID',
        public string $visitIpAddressColumn = 'IP address',
        public string $visitEmailColumn = 'Email address',
        public string $tokenCreatedDateColumn = 'Created (date)',
        public string $tokenCreatedClientColumn = 'Created (client)',
        public string $tokenClaimedDateColumn = 'Redeemed (date)',
        public string $tokenClaimedClientColumn = 'Redeemed (client)',
        public string $tokenColumn = 'Token',
        public string $authResultUndefined = 'Unknown',
        public string $authResultSuccessfulPasswordLogin = 'Password login',
        public string $authResultErrorNoEmailAddress = 'No email address',
        public string $authResultErrorNoPassword = 'No password',
        public string $authResultErrorUnknownUserName = 'Invalid email address',
        public string $authResultErrorInactive = 'Account inactive',
        public string $authResultErrorIpNotAllowed = 'IP address not allowed',
        public string $authResultErrorOutTried = 'Too many failed attempts',
        public string $authResultErrorWrongPassword = 'Wrong password',
        public string $authResultSuccessfulSsoLogin = 'SSO login',
        public string $authResultErrorNoPasswordLoginActive = 'Password login inactive',
        public string $authResultFailedSsoLogin = 'SSO failed',
        public string $authResultSuccessfulOtpLogin = 'OTP login',
        public string $authResultSuccessfulMicrosoftLogin = 'Microsoft login',
        public string $authTokenTypePassword = 'Password reset',
        public string $authTokenTypeActivation = 'Activation',
        public string $authTokenTypeLogin = 'Login',
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
            filterNoAccess: 'Kein Zugriff',
            filterUnconfirmedAccess: 'Unbestätigter Zugang',
            visitDateColumn: 'Datum',
            visitSessionIdColumn: 'SessionID',
            visitIpAddressColumn: 'IP-Adresse',
            visitEmailColumn: 'E-Mail-Adresse',
            tokenCreatedDateColumn: 'Erstellt (Datum)',
            tokenCreatedClientColumn: 'Erstellt (Client)',
            tokenClaimedDateColumn: 'Eingelöst (Datum)',
            tokenClaimedClientColumn: 'Eingelöst (Client)',
            tokenColumn: 'Token',
            authResultUndefined: 'Unbekannt',
            authResultSuccessfulPasswordLogin: 'Passwort-Anmeldung',
            authResultErrorNoEmailAddress: 'Keine E-Mail-Adresse',
            authResultErrorNoPassword: 'Kein Passwort',
            authResultErrorUnknownUserName: 'Ungültige E-Mail-Adresse',
            authResultErrorInactive: 'Zugang inaktiv',
            authResultErrorIpNotAllowed: 'IP-Adresse nicht erlaubt',
            authResultErrorOutTried: 'Zu viele fehlerhafte Versuche',
            authResultErrorWrongPassword: 'Falsches Passwort',
            authResultSuccessfulSsoLogin: 'SSO-Anmeldung',
            authResultErrorNoPasswordLoginActive: 'Passwort-Anmeldung inaktiv',
            authResultFailedSsoLogin: 'SSO fehlgeschlagen',
            authResultSuccessfulOtpLogin: 'OTP-Anmeldung',
            authResultSuccessfulMicrosoftLogin: 'Microsoft-Anmeldung',
            authTokenTypePassword: 'Passwort-Reset',
            authTokenTypeActivation: 'Aktivierung',
            authTokenTypeLogin: 'Anmeldung',
        );
    }

    /**
     * Label of a visit status (replaces yuf's German AuthResultEnum::render()).
     */
    public function authResult(AuthResultEnum $authResult): string
    {
        return match ($authResult) {
            AuthResultEnum::UNDEFINED => $this->authResultUndefined,
            AuthResultEnum::SUCCESSFUL_PASSWORD_LOGIN => $this->authResultSuccessfulPasswordLogin,
            AuthResultEnum::ERROR_NO_EMAIL_ADDRESS => $this->authResultErrorNoEmailAddress,
            AuthResultEnum::ERROR_NO_PASSWORD => $this->authResultErrorNoPassword,
            AuthResultEnum::ERROR_UNKNOWN_USER_NAME => $this->authResultErrorUnknownUserName,
            AuthResultEnum::ERROR_INACTIVE => $this->authResultErrorInactive,
            AuthResultEnum::ERROR_IP_NOT_ALLOWED => $this->authResultErrorIpNotAllowed,
            AuthResultEnum::ERROR_OUT_TRIED => $this->authResultErrorOutTried,
            AuthResultEnum::ERROR_WRONG_PASSWORD => $this->authResultErrorWrongPassword,
            AuthResultEnum::SUCCESSFUL_SSO_LOGIN => $this->authResultSuccessfulSsoLogin,
            AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE => $this->authResultErrorNoPasswordLoginActive,
            AuthResultEnum::FAILED_SSO_LOGIN => $this->authResultFailedSsoLogin,
            AuthResultEnum::SUCCESSFUL_OTP_LOGIN => $this->authResultSuccessfulOtpLogin,
            AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN => $this->authResultSuccessfulMicrosoftLogin,
        };
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
