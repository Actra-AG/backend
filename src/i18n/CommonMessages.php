<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts used by several areas of the backend (buttons, user data fields, passwords, IP whitelist, search, tables).
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class CommonMessages
{
    use OverridableMessages;

    public function __construct(
        public string $save = 'Save',
        public string $send = 'Send',
        public string $noChanges = 'No changes have been made.',
        public string $changesSaved = 'The changes have been saved.',
        public string $successLabel = 'Success:',
        public string $noteLabel = 'Note:',
        public string $removeLink = 'Remove',
        public string $firstNameLabel = 'First name',
        public string $firstNameRequired = 'Please enter the first name.',
        public string $lastNameLabel = 'Last name',
        public string $lastNameRequired = 'Please enter the last name.',
        public string $emailLabel = 'Email',
        public string $emailInvalid = 'Please enter a valid email address.',
        public string $emailRequired = 'Please enter the email address.',
        public string $emailAlreadyInUse = 'The email address is already in use.',
        public string $phoneLabel = 'Phone',
        public string $phoneInvalid = 'Please enter a valid phone number.',
        public string $languageLabel = 'Language',
        public string $languageDefault = 'Default ([language])',
        public string $userGroupLabel = 'User group',
        public string $userGroupsLabel = 'User groups',
        public string $ipWhitelistLabel = 'IP whitelist',
        public string $ipWhitelistInfo = 'One IP address per line.',
        public string $ipWhitelistInvalid = 'Invalid IP address [ipAddress]',
        public string $apiKeyLabel = 'API key',
        public string $apiKeyValueLabel = 'API key:',
        public string $apiKeyNone = 'none',
        public string $apiKeyGenerated = 'The new API key has been generated. It will not be shown again.',
        public string $generateApiKeyLink = 'Generate new',
        public string $apiKeyNeedsIpWhitelist = 'API keys can only be generated once an IP whitelist has been set.',
        public string $apiKeyBlocksEmptyIpWhitelist = 'The API key must be removed before the IP whitelist can be emptied.',
        public string $newPasswordLabel = 'New password',
        public string $newPasswordRequired = 'Please enter the new password.',
        public string $newPasswordConfirmLabel = 'Confirm new password',
        public string $newPasswordConfirmRequired = 'Please confirm the new password.',
        public string $newPasswordTooShort = 'The new password must be at least [minLength] characters long.',
        public string $newPasswordsDoNotMatch = 'The new passwords do not match.',
        public string $subjectLabel = 'Subject',
        public string $subjectRequired = 'Please enter a subject.',
        public string $messageBodyLabel = 'Message',
        public string $messageBodyRequired = 'Please enter the message.',
        public string $closingGreeting = 'Kind regards',
        public string $searchLabel = 'Search term',
        public string $searchButton = 'Show',
        public string $filterAll = 'all',
        public string $tableNoEntries = 'No entries found.',
        public string $tableOneResult = '[count] result found.',
        public string $tableResults = '[count] results found.',
        public string $paginationPrevious = 'Previous',
        public string $paginationNext = 'Next',
        public string $dateFormat = 'Y-m-d',
        public string $dateTimeFormat = 'Y-m-d H:i:s',
        public string $removeApiKeyTitle = 'Remove API key',
        public string $removeApiKeyConfirm = 'Really remove the API key?'
    ) {
    }

    public static function german(): CommonMessages
    {
        return new CommonMessages(
            save: 'Speichern',
            send: 'Senden',
            noChanges: 'Es wurden keine Änderungen vorgenommen.',
            changesSaved: 'Die Änderungen wurden gespeichert.',
            successLabel: 'Erfolgreich:',
            noteLabel: 'Hinweis:',
            removeLink: 'Entfernen',
            firstNameLabel: 'Vorname',
            firstNameRequired: 'Bitte geben Sie den Vornamen ein.',
            lastNameLabel: 'Nachname',
            lastNameRequired: 'Bitte geben Sie den Nachnamen ein.',
            emailLabel: 'E-Mail',
            emailInvalid: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            emailRequired: 'Bitte geben Sie die E-Mail-Adresse ein.',
            emailAlreadyInUse: 'Die eingegebene E-Mail-Adresse wird bereits verwendet.',
            phoneLabel: 'Telefon',
            phoneInvalid: 'Bitte geben Sie eine gültige Telefonnummer ein.',
            languageLabel: 'Sprache',
            languageDefault: 'Standard ([language])',
            userGroupLabel: 'Benutzergruppe',
            userGroupsLabel: 'Benutzergruppen',
            ipWhitelistLabel: 'IP-Whitelist',
            ipWhitelistInfo: 'Eine IP-Adresse pro Zeile.',
            ipWhitelistInvalid: 'Ungültige IP-Adresse [ipAddress]',
            apiKeyLabel: 'API-Key',
            apiKeyValueLabel: 'API-Key:',
            apiKeyNone: 'keiner',
            apiKeyGenerated: 'Der neue API-Key wurde generiert. Er wird später nicht erneut angezeigt.',
            generateApiKeyLink: 'Neu generieren',
            apiKeyNeedsIpWhitelist: 'API-Keys können erst generiert werden, wenn eine IP-Whitelist hinterlegt ist.',
            apiKeyBlocksEmptyIpWhitelist: 'Der API-Key muss entfernt werden, bevor die IP-Whitelist geleert werden kann.',
            newPasswordLabel: 'Neues Passwort',
            newPasswordRequired: 'Bitte geben Sie das neue Passwort ein.',
            newPasswordConfirmLabel: 'Neues Passwort bestätigen',
            newPasswordConfirmRequired: 'Bitte bestätigen Sie das neue Passwort.',
            newPasswordTooShort: 'Das neue Passwort muss mindestens [minLength] Zeichen lang sein.',
            newPasswordsDoNotMatch: 'Die neuen Passwörter stimmen nicht überein.',
            subjectLabel: 'Betreff',
            subjectRequired: 'Geben Sie bitte ein Betreff ein.',
            messageBodyLabel: 'Textinhalt',
            messageBodyRequired: 'Geben Sie bitte den gewünschten Text ein.',
            closingGreeting: 'Freundliche Grüsse',
            searchLabel: 'Suchbegriff',
            searchButton: 'anzeigen',
            filterAll: 'alle',
            tableNoEntries: 'Es wurden keine Einträge gefunden.',
            tableOneResult: 'Es wurde [count] Resultat gefunden.',
            tableResults: 'Es wurden [count] Resultate gefunden.',
            paginationPrevious: 'Zurück',
            paginationNext: 'Vor',
            dateFormat: 'd.m.Y',
            dateTimeFormat: 'd.m.Y H:i:s',
            removeApiKeyTitle: 'API-Key entfernen',
            removeApiKeyConfirm: 'Den API-Key wirklich entfernen?'
        );
    }
}