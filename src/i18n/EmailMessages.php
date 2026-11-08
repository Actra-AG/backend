<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of the emails sent by the backend (plain text, placeholders in square brackets). `signature` follows the
 * closing greeting (`CommonMessages::$closingGreeting`); its default is the sender name (`MailerSettings`).
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
        public string $signature = '[senderName]',
    ) {}

    public static function german(): EmailMessages
    {
        return new EmailMessages(
            greeting: 'Grüezi',
            loginTokenSubject: '[token] ist Ihr Bestätigungscode',
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

    public static function french(): EmailMessages
    {
        return new EmailMessages(
            greeting: 'Bonjour',
            loginTokenSubject: '[token] est votre code de confirmation',
            loginTokenIntroPasswordLogin: 'Le code de confirmation suivant vous permet de vous connecter en toute '
                . 'sécurité au backend:',
            loginTokenIntroPasswordless: 'Le code de confirmation suivant vous permet de vous connecter au backend '
                . 'en toute sécurité et sans mot de passe:',
            loginTokenValidity: 'Veuillez noter que ce code ne peut être utilisé qu’une seule fois et qu’il expire '
                . 'après [minutes] minutes.',
            loginTokenIgnore: 'Si vous n’avez pas demandé de code de confirmation pour l’adresse e-mail [email], '
                . 'vous pouvez ignorer cet e-mail.',
            passwordResetSubject: 'Votre nouveau mot de passe',
            passwordResetIntro: 'Vous avez indiqué sur [host] avoir oublié votre mot de passe.',
            passwordResetLinkInstruction: 'Cliquez sur le lien suivant pour choisir un nouveau mot de passe:',
            passwordResetValidity: 'Veuillez noter que ce lien ne peut être utilisé qu’une seule fois et qu’il '
                . 'expire après [minutes] minutes.',
            passwordResetIgnore: 'Si vous ne souhaitez pas réinitialiser le mot de passe de l’adresse e-mail '
                . '[email], vous pouvez ignorer cet e-mail.',
        );
    }

    public static function italian(): EmailMessages
    {
        return new EmailMessages(
            greeting: 'Buongiorno',
            loginTokenSubject: '[token] è il Suo codice di conferma',
            loginTokenIntroPasswordLogin: 'Con il seguente codice di conferma può accedere al backend in modo sicuro:',
            loginTokenIntroPasswordless: 'Con il seguente codice di conferma può accedere al backend in modo sicuro '
                . 'e senza password:',
            loginTokenValidity: 'La preghiamo di notare che questo codice può essere utilizzato una sola volta e '
                . 'scade dopo [minutes] minuti.',
            loginTokenIgnore: 'Se non ha richiesto un codice di conferma per l’indirizzo e-mail [email], può '
                . 'ignorare questa e-mail.',
            passwordResetSubject: 'La Sua nuova password',
            passwordResetIntro: 'Ha indicato su [host] di aver dimenticato la password.',
            passwordResetLinkInstruction: 'Clicchi sul link seguente per scegliere una nuova password:',
            passwordResetValidity: 'La preghiamo di notare che questo link può essere utilizzato una sola volta e '
                . 'scade dopo [minutes] minuti.',
            passwordResetIgnore: 'Se non desidera reimpostare la password per l’indirizzo e-mail [email], può '
                . 'ignorare questa e-mail.',
        );
    }
}
