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
        public string $loginIntro = 'Enter the email address you are registered with. You will receive a code that you '
            . 'can enter in the next step to log in.',
        public string $loginPasswordIntro = 'Enter the credentials you are registered with.',
        public string $loginTokenIntro = 'We have sent a code to the email address you entered in the previous step. '
            . 'Enter this code in the following field to log in.',
        public string $loginTokenTipLabel = 'Tip:',
        public string $loginTokenTipText = 'No email in your inbox? Check your spam folder as well.',
        public string $backToLogin = 'Back to login',
        public string $logoutHeading = 'Goodbye',
        public string $logoutStatus = 'You have been logged out successfully.',
        public string $loginLink = 'Log in',
        public string $loginTicketHeading = 'Login not completed',
        public string $loginTicketInvalid = 'The login could not be completed: the link is invalid or has expired. '
            . 'Please log in again.',
        public string $passwordForgottenIntro = 'Enter the email address you are registered with. You will then '
            . 'receive an email with a link to reset your password.',
        public string $passwordForgottenResult = 'If the email address is registered with us, you will receive an '
            . 'automatic email within the next few minutes. It contains a link to reset your password.',
        public string $passwordResetIntro = 'Enter your new password below.',
        public string $passwordResetDone = 'Your new password has been saved.',
        public string $emailInvalid = 'You have entered an invalid email address.',
        public string $emailRequired = 'Enter your email address.',
        public string $passwordLabel = 'Password',
        public string $passwordRequired = 'Enter your password.',
        public string $credentialsInvalid = 'The credentials you entered are invalid.',
        public string $tooManyAttempts = 'Too many attempts. Please try again in a few minutes.',
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
            loginIntro: 'Geben Sie nachfolgend die E-Mail-Adresse ein, mit welcher Sie hier registriert sind. Sie '
                . 'erhalten dann einen Code, den Sie im nächsten Schritt eingeben können, um sich anzumelden.',
            loginPasswordIntro: 'Geben Sie nachfolgend die Zugangsdaten ein, mit denen Sie registriert sind.',
            loginTokenIntro: 'Wir haben Ihnen einen Code an die E-Mail-Adresse geschickt, die Sie im vorherigen '
                . 'Schritt angegeben haben. Geben Sie diesen Code im folgenden Feld ein, um sich anzumelden.',
            loginTokenTipLabel: 'Tipp:',
            loginTokenTipText: 'keine E-Mail im Postfach? Prüfen Sie auch den Spam-Ordner.',
            backToLogin: 'Zurück zur Anmeldung',
            logoutHeading: 'Auf Wiedersehen',
            logoutStatus: 'Sie wurden erfolgreich abgemeldet.',
            loginLink: 'Anmelden',
            loginTicketHeading: 'Anmeldung nicht abgeschlossen',
            loginTicketInvalid: 'Die Anmeldung konnte nicht abgeschlossen werden: Der Link ist ungültig oder '
                . 'abgelaufen. Bitte melden Sie sich erneut an.',
            passwordForgottenIntro: 'Geben Sie die E-Mail-Adresse ein, mit der Sie registriert sind. Sie erhalten '
                . 'danach eine E-Mail mit einem Link zum Zurücksetzen Ihres Passworts.',
            passwordForgottenResult: 'Falls die E-Mail-Adresse bei uns registriert ist, erhalten Sie in den nächsten '
                . 'Minuten eine automatische E-Mail. In dieser E-Mail befindet sich ein Link, um das Passwort '
                . 'zurückzusetzen.',
            passwordResetIntro: 'Geben Sie nachfolgend Ihr gewünschtes neues Passwort ein.',
            passwordResetDone: 'Das neue Passwort wurde gespeichert.',
            emailInvalid: 'Sie haben eine ungültige E-Mail-Adresse eingegeben.',
            emailRequired: 'Geben Sie Ihre E-Mail-Adresse ein.',
            passwordLabel: 'Passwort',
            passwordRequired: 'Geben Sie Ihr Passwort ein.',
            credentialsInvalid: 'Die eingegebenen Zugangsdaten sind ungültig.',
            tooManyAttempts: 'Zu viele Versuche. Bitte versuchen Sie es in einigen Minuten erneut.',
            loginSubmitLabel: 'weiter',
            loginPasswordSubmitLabel: 'Weiter',
            passwordForgottenLinkLabel: 'Passwort vergessen?',
            tokenLabel: 'Code',
            tokenRequired: 'Geben Sie den Code ein.',
            tokenInvalid: 'Sie haben einen ungültigen Code eingegeben.',
            tokenSubmitLabel: 'anmelden',
        );
    }

    public static function french(): AuthMessages
    {
        return new AuthMessages(
            loginPageTitle: 'Connexion',
            logoutPageTitle: 'Déconnexion',
            passwordForgottenPageTitle: 'Mot de passe oublié?',
            passwordResetPageTitle: 'Réinitialiser le mot de passe',
            loginIntro: 'Saisissez ci-dessous l’adresse e-mail que vous avez enregistrée chez nous. Vous recevrez '
                . 'un code que vous pourrez saisir à l’étape suivante pour vous connecter.',
            loginPasswordIntro: 'Saisissez ci-dessous vos données d’accès.',
            loginTokenIntro: 'Nous avons envoyé un code à l’adresse e-mail saisie à l’étape précédente. Saisissez '
                . 'ce code dans le champ ci-dessous pour vous connecter.',
            loginTokenTipLabel: 'Conseil:',
            loginTokenTipText: 'pas d’e-mail dans votre boîte de réception? Vérifiez aussi le dossier des spams.',
            backToLogin: 'Retour à la connexion',
            logoutHeading: 'Au revoir',
            logoutStatus: 'La déconnexion a été effectuée.',
            loginLink: 'Se connecter',
            loginTicketHeading: 'Connexion non terminée',
            loginTicketInvalid: 'La connexion n’a pas pu être terminée: le lien n’est pas valable ou a expiré. '
                . 'Veuillez vous reconnecter.',
            passwordForgottenIntro: 'Saisissez l’adresse e-mail que vous avez enregistrée chez nous. Vous recevrez '
                . 'ensuite un e-mail avec un lien pour réinitialiser votre mot de passe.',
            passwordForgottenResult: 'Si l’adresse e-mail est enregistrée chez nous, vous recevrez un e-mail '
                . 'automatique dans les prochaines minutes. Il contient un lien pour réinitialiser le mot de passe.',
            passwordResetIntro: 'Saisissez ci-dessous votre nouveau mot de passe.',
            passwordResetDone: 'Le nouveau mot de passe a été enregistré.',
            emailInvalid: 'Vous avez saisi une adresse e-mail non valable.',
            emailRequired: 'Saisissez votre adresse e-mail.',
            passwordLabel: 'Mot de passe',
            passwordRequired: 'Saisissez votre mot de passe.',
            credentialsInvalid: 'Les données d’accès saisies ne sont pas valables.',
            tooManyAttempts: 'Trop de tentatives. Veuillez réessayer dans quelques minutes.',
            loginSubmitLabel: 'continuer',
            loginPasswordSubmitLabel: 'Continuer',
            passwordForgottenLinkLabel: 'Mot de passe oublié?',
            tokenLabel: 'Code',
            tokenRequired: 'Saisissez le code.',
            tokenInvalid: 'Vous avez saisi un code non valable.',
            tokenSubmitLabel: 'se connecter',
        );
    }

    public static function italian(): AuthMessages
    {
        return new AuthMessages(
            loginPageTitle: 'Accesso',
            logoutPageTitle: 'Disconnessione',
            passwordForgottenPageTitle: 'Password dimenticata?',
            passwordResetPageTitle: 'Reimpostare la password',
            loginIntro: 'Inserisca qui sotto l’indirizzo e-mail registrato presso di noi. Riceverà un codice da '
                . 'inserire nel passo successivo per accedere.',
            loginPasswordIntro: 'Inserisca qui sotto i Suoi dati di accesso.',
            loginTokenIntro: 'Abbiamo inviato un codice all’indirizzo e-mail indicato nel passo precedente. '
                . 'Inserisca questo codice nel campo seguente per accedere.',
            loginTokenTipLabel: 'Suggerimento:',
            loginTokenTipText: 'nessuna e-mail nella casella di posta? Controlli anche la cartella dello spam.',
            backToLogin: 'Torna all’accesso',
            logoutHeading: 'Arrivederci',
            logoutStatus: 'La disconnessione è avvenuta con successo.',
            loginLink: 'Accedere',
            loginTicketHeading: 'Accesso non completato',
            loginTicketInvalid: 'Non è stato possibile completare l’accesso: il link non è valido o è scaduto. La '
                . 'preghiamo di accedere di nuovo.',
            passwordForgottenIntro: 'Inserisca l’indirizzo e-mail registrato presso di noi. Riceverà poi un’e-mail '
                . 'con un link per reimpostare la Sua password.',
            passwordForgottenResult: 'Se l’indirizzo e-mail è registrato presso di noi, nei prossimi minuti '
                . 'riceverà un’e-mail automatica. Contiene un link per reimpostare la password.',
            passwordResetIntro: 'Inserisca qui sotto la nuova password.',
            passwordResetDone: 'La nuova password è stata salvata.',
            emailInvalid: 'Ha inserito un indirizzo e-mail non valido.',
            emailRequired: 'Inserisca il Suo indirizzo e-mail.',
            passwordLabel: 'Password',
            passwordRequired: 'Inserisca la Sua password.',
            credentialsInvalid: 'I dati di accesso inseriti non sono validi.',
            tooManyAttempts: 'Troppi tentativi. Riprovi tra qualche minuto.',
            loginSubmitLabel: 'continuare',
            loginPasswordSubmitLabel: 'Continuare',
            passwordForgottenLinkLabel: 'Password dimenticata?',
            tokenLabel: 'Codice',
            tokenRequired: 'Inserisca il codice.',
            tokenInvalid: 'Ha inserito un codice non valido.',
            tokenSubmitLabel: 'accedere',
        );
    }
}
