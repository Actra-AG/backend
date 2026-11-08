<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of the profile of the logged-in user.
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class ProfileMessages
{
    use OverridableMessages;

    public function __construct(
        public string $profilePageTitle = 'My profile',
        public string $changePasswordPageTitle = 'Change password',
        public string $createPasswordPageTitle = 'Create password',
        public string $removePasswordPageTitle = 'Remove password',
        public string $ipWhitelistInfo = 'One IP address per line. Make sure not to exclude your current IP address.',
        public string $currentIpMustBeAllowed = 'The IP whitelist must allow your current IP address [ipAddress].',
        public string $passwordProtectionHeading = 'Password protection',
        public string $passwordProtectionIntro = 'In addition to the one-time code sent by email, you can protect your '
            . 'access to the backend with a password.',
        public string $createPasswordLink = 'Create password',
        public string $passwordLoginIntro = 'Password login uses a separate login page:',
        public string $passwordLabel = 'Password',
        public string $changeLink = 'Change',
        public string $currentPasswordLabel = 'Current password',
        public string $currentPasswordRequired = 'Please enter the current password.',
        public string $currentPasswordIncorrect = 'The current password is not correct.',
    ) {}

    public static function german(): ProfileMessages
    {
        return new ProfileMessages(
            profilePageTitle: 'Mein Profil',
            changePasswordPageTitle: 'Passwort ändern',
            createPasswordPageTitle: 'Passwort erstellen',
            removePasswordPageTitle: 'Passwort entfernen',
            ipWhitelistInfo: 'Eine IP-Adresse pro Zeile. Achten Sie darauf, Ihre aktuelle IP-Adresse nicht '
                . 'auszuschliessen.',
            currentIpMustBeAllowed: 'Die IP-Whitelist muss Ihre aktuelle IP-Adresse [ipAddress] erlauben.',
            passwordProtectionHeading: 'Passwortschutz',
            passwordProtectionIntro: 'Zusätzlich zum per E-Mail verschickten Einmalcode können Sie Ihren Zugang ins '
                . 'GUI mit einem Passwort schützen.',
            createPasswordLink: 'Passwort erstellen',
            passwordLoginIntro: 'Die Anmeldung mit Passwort erfolgt über eine separate Anmeldemaske:',
            passwordLabel: 'Passwort',
            changeLink: 'Ändern',
            currentPasswordLabel: 'Aktuelles Passwort',
            currentPasswordRequired: 'Bitte geben Sie das aktuelle Passwort ein.',
            currentPasswordIncorrect: 'Das aktuelle Passwort ist nicht korrekt.',
        );
    }

    public static function french(): ProfileMessages
    {
        return new ProfileMessages(
            profilePageTitle: 'Mon profil',
            changePasswordPageTitle: 'Modifier le mot de passe',
            createPasswordPageTitle: 'Créer un mot de passe',
            removePasswordPageTitle: 'Supprimer le mot de passe',
            ipWhitelistInfo: 'Une adresse IP par ligne. Veillez à ne pas exclure votre adresse IP actuelle.',
            currentIpMustBeAllowed: 'La liste blanche IP doit autoriser votre adresse IP actuelle [ipAddress].',
            passwordProtectionHeading: 'Protection par mot de passe',
            passwordProtectionIntro: 'En plus du code à usage unique envoyé par e-mail, vous pouvez protéger votre '
                . 'accès au backend par un mot de passe.',
            createPasswordLink: 'Créer un mot de passe',
            passwordLoginIntro: 'La connexion par mot de passe se fait sur une page de connexion séparée:',
            passwordLabel: 'Mot de passe',
            changeLink: 'Modifier',
            currentPasswordLabel: 'Mot de passe actuel',
            currentPasswordRequired: 'Veuillez saisir le mot de passe actuel.',
            currentPasswordIncorrect: 'Le mot de passe actuel n’est pas correct.',
        );
    }

    public static function italian(): ProfileMessages
    {
        return new ProfileMessages(
            profilePageTitle: 'Il mio profilo',
            changePasswordPageTitle: 'Modificare la password',
            createPasswordPageTitle: 'Creare una password',
            removePasswordPageTitle: 'Rimuovere la password',
            ipWhitelistInfo: 'Un indirizzo IP per riga. Faccia attenzione a non escludere il Suo indirizzo IP attuale.',
            currentIpMustBeAllowed: 'La whitelist IP deve consentire il Suo indirizzo IP attuale [ipAddress].',
            passwordProtectionHeading: 'Protezione con password',
            passwordProtectionIntro: 'Oltre al codice monouso inviato per e-mail, può proteggere il Suo accesso al '
                . 'backend con una password.',
            createPasswordLink: 'Creare una password',
            passwordLoginIntro: 'L’accesso con password avviene tramite una pagina di accesso separata:',
            passwordLabel: 'Password',
            changeLink: 'Modificare',
            currentPasswordLabel: 'Password attuale',
            currentPasswordRequired: 'Inserisca la password attuale.',
            currentPasswordIncorrect: 'La password attuale non è corretta.',
        );
    }
}
