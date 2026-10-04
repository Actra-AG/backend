<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of user management.
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class UserMessages
{
    use OverridableMessages;

    public function __construct(
        public string $navigationUserList = 'User list',
        public string $usersTitle = 'Users',
        public string $addUserTitle = 'Add user',
        public string $editUserTitle = 'Edit user',
        public string $inviteTitle = 'Send welcome email',
        public string $impersonateButton = 'Impersonate user',
        public string $deleteButton = 'Delete user',
        public string $deleteConfirm = 'Really delete [name]?',
        public string $removedMessage = 'The entry has been deleted.',
        public string $addedMessage = 'The entry has been added.',
        public string $invitedMessage = 'The welcome email has been sent.',
        public string $notInvitedNote = 'This user has not received a welcome email yet.',
        public string $personalDataHeading = 'Personal data',
        public string $accessSettingsHeading = 'Access settings',
        public string $registeredLabel = 'Registered',
        public string $welcomeEmailSentLabel = 'Welcome email sent',
        public string $resendLink = 'Send again',
        public string $lastLoginLabel = 'Last login',
        public string $neverLabel = 'never',
        public string $accessActiveLabel = 'Access active?',
        public string $userGroupsDetailLabel = 'User group(s)',
        public string $yes = 'yes',
        public string $no = 'no',
        public string $statusActive = 'active',
        public string $statusInactive = 'inactive',
        public string $inviteIntro = 'Enter the content of the welcome email that is to be sent to the person below.',
        public string $recipientLabel = 'Recipient:',
        public string $inviteSubmitButton = 'Send',
        public string $inviteDefaultSubject = 'Access to the password-protected area',
        public string $inviteGreeting = 'Hello [firstName] [lastName]',
        public string $inviteAccessCreated = 'We have set up an account for you in our backend:',
        public string $inviteLoginInstructions = 'To log in, enter your email address [email] and, in the next step, the confirmation code you receive.',
        public string $activeAccessLabel = 'Active access',
        public string $userGroupsRequired = 'Please select at least one user group.',
        public string $nameColumn = 'Name',
        public string $activeColumn = 'Active',
        public string $rightGroupsColumn = 'Permission group(s)',
        public string $registeredColumn = 'Registered',
        public string $invitedColumn = 'Invited'
    ) {
    }

    public static function german(): UserMessages
    {
        return new UserMessages(
            navigationUserList: 'Benutzerliste',
            usersTitle: 'Benutzer',
            addUserTitle: 'Benutzer hinzufügen',
            editUserTitle: 'Benutzer bearbeiten',
            inviteTitle: 'Willkommens-E-Mail senden',
            impersonateButton: 'Benutzer verkörpern',
            deleteButton: 'Benutzer löschen',
            deleteConfirm: '[name] wirklich löschen?',
            removedMessage: 'Der Eintrag wurde gelöscht.',
            addedMessage: 'Der Eintrag wurde hinzugefügt.',
            invitedMessage: 'Die Willkommens-E-Mail wurde verschickt.',
            notInvitedNote: 'Dieser Benutzer hat noch keine Willkommens-E-Mail erhalten.',
            personalDataHeading: 'Persönliche Daten',
            accessSettingsHeading: 'Zugriffseinstellungen',
            registeredLabel: 'Registriert',
            welcomeEmailSentLabel: 'Willkommens-E-Mail verschickt',
            resendLink: 'Erneut senden',
            lastLoginLabel: 'Letzte Anmeldung',
            neverLabel: 'noch nie',
            accessActiveLabel: 'Zugang aktiv?',
            userGroupsDetailLabel: 'Benutzergruppe(n)',
            yes: 'ja',
            no: 'nein',
            statusActive: 'aktiv',
            statusInactive: 'inaktiv',
            inviteIntro: 'Geben Sie nachfolgend den gewünschten Inhalt der Willkommens-E-Mail ein, die an die Person geschickt werden soll.',
            recipientLabel: 'Empfänger:',
            inviteSubmitButton: 'senden',
            inviteDefaultSubject: 'Zugang zum passwortgeschützten Bereich',
            inviteGreeting: 'Guten Tag [firstName] [lastName]',
            inviteAccessCreated: 'Wir haben Ihnen einen Zugang in unser Backend eingerichtet:',
            inviteLoginInstructions: 'Geben Sie zur Anmeldung Ihre E-Mail-Adresse [email] und beim nächsten Schritt den erhaltenen Bestätigungscode ein, um sich anzumelden.',
            activeAccessLabel: 'aktiver Zugang',
            userGroupsRequired: 'Bitte wählen Sie mindestens eine Benutzergruppe aus.',
            nameColumn: 'Name',
            activeColumn: 'Aktiv',
            rightGroupsColumn: 'Rechtegruppe(n)',
            registeredColumn: 'erfasst',
            invitedColumn: 'eingeladen'
        );
    }
}