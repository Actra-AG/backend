<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of login, login token, logout and password reset.
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class AuthMessages
{
    use OverridableMessages;

    public function __construct(
        public string $loginPageTitle = 'Log in',
        public string $logoutPageTitle = 'Log out',
        public string $passwordForgottenPageTitle = 'Forgot your password?',
        public string $passwordResetPageTitle = 'Reset password',
        public string $loginIntro = 'Enter the email address you are registered with. You will receive a code that you can enter in the next step to log in.',
        public string $loginPasswordIntro = 'Enter the credentials you are registered with.',
        public string $loginTokenIntro = 'We have sent a code to the email address you entered in the previous step. Enter this code in the following field to log in.',
        public string $loginTokenTipLabel = 'Tip:',
        public string $loginTokenTipText = 'No email in your inbox? Check your spam folder as well.',
        public string $backToLogin = 'Back to login',
        public string $logoutHeading = 'Goodbye',
        public string $logoutStatus = 'You have been logged out successfully.',
        public string $loginLink = 'Log in',
        public string $passwordForgottenIntro = 'Enter the email address you are registered with. You will then receive an email with a link to reset your password.',
        public string $passwordForgottenResult = 'If the email address is registered with us, you will receive an automatic email within the next few minutes. It contains a link to reset your password.',
        public string $passwordResetIntro = 'Enter your new password below.',
        public string $passwordResetDone = 'Your new password has been saved.',
        public string $emailInvalid = 'You have entered an invalid email address.',
        public string $emailRequired = 'Enter your email address.',
        public string $passwordLabel = 'Password',
        public string $passwordRequired = 'Enter your password.',
        public string $credentialsInvalid = 'The credentials you entered are invalid.',
        public string $loginSubmitLabel = 'Continue',
        public string $loginPasswordSubmitLabel = 'Continue',
        public string $passwordForgottenLinkLabel = 'Forgot your password?',
        public string $tokenLabel = 'Code',
        public string $tokenRequired = 'Enter the code.',
        public string $tokenInvalid = 'You have entered an invalid code.',
        public string $tokenSubmitLabel = 'Log in',
    ) {}

    public static function german(): AuthMessages
    {
        return new AuthMessages(
            loginPageTitle: 'Anmelden',
            logoutPageTitle: 'Abmelden',
            passwordForgottenPageTitle: 'Passwort vergessen?',
            passwordResetPageTitle: 'Passwort zurücksetzen',
            loginIntro: 'Geben Sie nachfolgend die E-Mail-Adresse ein, mit welcher Sie hier registriert sind. Sie erhalten dann einen Code, den Sie im nächsten Schritt eingeben können, um sich anzumelden.',
            loginPasswordIntro: 'Geben Sie nachfolgend die Zugangsdaten ein, mit denen Sie registriert sind.',
            loginTokenIntro: 'Wir haben Ihnen einen Code an die E-Mail-Adresse geschickt, die Sie im vorherigen Schritt angegeben haben. Geben Sie diesen Code im folgenden Feld ein, um sich anzumelden.',
            loginTokenTipLabel: 'Tipp:',
            loginTokenTipText: 'keine E-Mail im Postfach? Prüfen Sie auch den Spam-Ordner.',
            backToLogin: 'Zurück zur Anmeldung',
            logoutHeading: 'Auf Wiedersehen',
            logoutStatus: 'Sie wurden erfolgreich abgemeldet.',
            loginLink: 'Anmelden',
            passwordForgottenIntro: 'Geben Sie die E-Mail-Adresse ein, mit der Sie registriert sind. Sie erhalten danach eine E-Mail mit einem Link zum Zurücksetzen Ihres Passworts.',
            passwordForgottenResult: 'Falls die E-Mail-Adresse bei uns registriert ist, erhalten Sie in den nächsten Minuten eine automatische E-Mail. In dieser E-Mail befindet sich ein Link, um das Passwort zurückzusetzen.',
            passwordResetIntro: 'Geben Sie nachfolgend ihr gewünschtes neues Passwort ein.',
            passwordResetDone: 'Das neue Passwort wurde gespeichert.',
            emailInvalid: 'Sie haben eine ungültige E-Mail-Adresse eingegeben.',
            emailRequired: 'Geben Sie Ihre E-Mail-Adresse ein.',
            passwordLabel: 'Passwort',
            passwordRequired: 'Geben Sie Ihr Passwort ein.',
            credentialsInvalid: 'Die eingegebenen Zugangsdaten sind ungültig.',
            loginSubmitLabel: 'weiter',
            loginPasswordSubmitLabel: 'Weiter',
            passwordForgottenLinkLabel: 'Passwort vergessen?',
            tokenLabel: 'Code',
            tokenRequired: 'Geben Sie den Code ein.',
            tokenInvalid: 'Sie haben einen ungültigen Code eingegeben.',
            tokenSubmitLabel: 'anmelden',
        );
    }
}
