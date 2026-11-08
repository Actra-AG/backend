<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of page layout, navigation and breadcrumbs; `frontendLink` is the text of the link to
 * `ActraBackendSettings::$frontendHref`.
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class LayoutMessages
{
    use OverridableMessages;

    public function __construct(
        public string $skipLink = 'Skip to content',
        public string $navigationTitleUsers = 'Users',
        public string $openMenu = 'Open menu',
        public string $closeMenu = 'Close menu',
        public string $languageSwitcherLabel = 'Change language',
        public string $cancelSessionChange = 'Cancel session change',
        public string $myProfile = 'My profile',
        public string $logout = 'Log out',
        public string $deleteConfirmation = 'Really delete?',
        public string $dialogCancel = 'Cancel',
        public string $dialogConfirmDelete = 'Yes, delete',
        public string $frontendLink = 'Go to website',
    ) {}

    public static function german(): LayoutMessages
    {
        return new LayoutMessages(
            skipLink: 'Direkt zum Inhalt',
            navigationTitleUsers: 'Benutzer',
            openMenu: 'Menü öffnen',
            closeMenu: 'Menü schliessen',
            languageSwitcherLabel: 'Sprache wechseln',
            cancelSessionChange: 'Sitzungswechsel abbrechen',
            myProfile: 'Mein Profil',
            logout: 'Abmelden',
            deleteConfirmation: 'Wirklich löschen?',
            dialogCancel: 'Abbrechen',
            dialogConfirmDelete: 'Ja, löschen',
            frontendLink: 'Zur Website',
        );
    }

    public static function french(): LayoutMessages
    {
        return new LayoutMessages(
            skipLink: 'Aller au contenu',
            navigationTitleUsers: 'Utilisateurs',
            openMenu: 'Ouvrir le menu',
            closeMenu: 'Fermer le menu',
            languageSwitcherLabel: 'Changer de langue',
            cancelSessionChange: 'Annuler le changement de session',
            myProfile: 'Mon profil',
            logout: 'Se déconnecter',
            deleteConfirmation: 'Vraiment supprimer?',
            dialogCancel: 'Annuler',
            dialogConfirmDelete: 'Oui, supprimer',
            frontendLink: 'Vers le site web',
        );
    }

    public static function italian(): LayoutMessages
    {
        return new LayoutMessages(
            skipLink: 'Passare al contenuto',
            navigationTitleUsers: 'Utenti',
            openMenu: 'Aprire il menu',
            closeMenu: 'Chiudere il menu',
            languageSwitcherLabel: 'Cambiare lingua',
            cancelSessionChange: 'Annullare il cambio di sessione',
            myProfile: 'Il mio profilo',
            logout: 'Disconnettersi',
            deleteConfirmation: 'Eliminare davvero?',
            dialogCancel: 'Annullare',
            dialogConfirmDelete: 'Sì, eliminare',
            frontendLink: 'Al sito web',
        );
    }
}
