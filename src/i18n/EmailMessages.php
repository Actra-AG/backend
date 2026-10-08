<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of the emails sent by the backend (plain text, placeholders in square brackets).
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class EmailMessages
{
    use OverridableMessages;

    public function __construct(
        public string $greeting = 'Hello',
        public string $loginTokenSubject = '[token] is your confirmation code',
        public string $loginTokenIntroPasswordLogin = 'You can securely log in to the backend with the following '
            . 'confirmation code:',
        public string $loginTokenIntroPasswordless = 'You can securely log in to the backend without a password using '
            . 'the following confirmation code:',
        public string $loginTokenValidity = 'Please note that this code can only be used once and expires after '
            . '[minutes] minutes.',
        public string $loginTokenIgnore = 'If you did not request a confirmation code for the email address [email], '
            . 'you can ignore this email.',
        public string $passwordResetSubject = 'Your new password',
        public string $passwordResetIntro = 'You told [host] that you have forgotten your password.',
        public string $passwordResetLinkInstruction = 'Click the following link to choose a new password:',
        public string $passwordResetValidity = 'Please note that this link can only be used once and expires after '
            . '[minutes] minutes.',
        public string $passwordResetIgnore = 'If you do not want to reset the password for the email address [email], '
            . 'you can ignore this email.',
    ) {}

    public static function german(): EmailMessages
    {
        return new EmailMessages(
            greeting: 'Grüezi',
            loginTokenSubject: '[token] ist ihr Bestätigungscode',
            loginTokenIntroPasswordLogin: 'Mit dem nachfolgenden Bestätigungscode können Sie sich sicher im Backend '
                . 'anmelden:',
            loginTokenIntroPasswordless: 'Mit dem nachfolgenden Bestätigungscode können Sie sich ohne Passwort sicher '
                . 'im Backend anmelden:',
            loginTokenValidity: 'Bitte beachten Sie, dass dieser Code nur einmal verwendet werden kann und nach '
                . '[minutes] Minuten verfällt.',
            loginTokenIgnore: 'Wenn Sie keinen Bestätigungscode für die E-Mail-Adresse [email] angefordert haben, '
                . 'können Sie diese E-Mail ignorieren.',
            passwordResetSubject: 'Ihr neues Passwort',
            passwordResetIntro: 'Sie haben bei [host] angegeben, dass Sie das Passwort vergessen haben.',
            passwordResetLinkInstruction: 'Klicken Sie auf den folgenden Link, um ein neues Passwort zu wählen:',
            passwordResetValidity: 'Bitte beachten Sie, dass dieser Link nur einmal verwendet werden kann und nach '
                . '[minutes] Minuten verfällt.',
            passwordResetIgnore: 'Wenn Sie das Passwort für die E-Mail-Adresse [email] nicht zurücksetzen möchten, '
                . 'können Sie diese E-Mail ignorieren.',
        );
    }
}
