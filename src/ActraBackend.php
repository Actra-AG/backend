<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\autoloader\Autoloader;
use actra\autoloader\AutoloaderPath;
use actra\backend\settings\MailerSettings;
use actra\backend\view\backend\php\login;
use actra\backend\view\backend\php\notifications;
use actra\backend\view\backend\php\tokens;
use actra\backend\view\backend\php\users;
use actra\backend\view\backend\php\visits;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\ContentType;
use actra\yuf\core\Language;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\db\DbSettingsModel;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;

Autoloader::get()->addPath(
    autoloaderPath: new AutoloaderPath(
        path: __DIR__ . DIRECTORY_SEPARATOR,
        prefix: 'actra\\backend\\'
    )
);

class ActraBackend
{
    public const string viewGroup = 'backend';
    public const string RIGHT_BACKEND_ACCESS = 'backend_access';
    public const string RIGHT_MANAGE_USERS = 'manage_users';
    private static ActraBackend $instance;

    private function __construct(
        public readonly string $path,
        public readonly array $ipWhitelist,
        public readonly string $backendName,
        public readonly array $javaScriptPaths,
        public readonly string $stylesHref,
        public readonly DbSettingsModel $dbSettingsModel,
        public readonly int $maxAllowedLoginAttempts,
        public readonly MailerSettings $mailerSettings,
        public readonly NavigationItemCollection $navigationItemCollection,
        public readonly string $frontendHref,
        public readonly string $frontendName,
        public readonly string $templateDirectory
    ) {
    }

    public function renderJavaScriptPaths(): HtmlDataObjectCollection
    {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        foreach ($this->javaScriptPaths as $path) {
            $htmlDataObject = new HtmlDataObject();
            $htmlDataObject->addTextElement(
                propertyName: 'src',
                content: $path,
                isEncodedForRendering: true
            );
            $htmlDataObjectCollection->add(htmlDataObject: $htmlDataObject);
        }
        return $htmlDataObjectCollection;
    }

    public static function init(
        RouteCollection $routeCollection,
        string $path,
        bool $isDefaultForLanguage,
        Language $language,
        array $ipWhitelist,
        string $backendName,
        array $javaScriptPaths,
        string $stylesHref,
        DbSettingsModel $dbSettingsModel,
        MailerSettings $mailerSettings,
        NavigationItemCollection $navigationItemCollection,
        int $maxAllowedLoginAttempts = 5,
        ?string $frontendHref = '',
        ?string $frontendName = '',
        ?string $templateDirectory = __DIR__ . '/view/backend/templates/',
    ): void {
        ActraBackend::$instance = new ActraBackend(
            path: $path,
            ipWhitelist: $ipWhitelist,
            backendName: $backendName,
            javaScriptPaths: $javaScriptPaths,
            stylesHref: $stylesHref,
            dbSettingsModel: $dbSettingsModel,
            maxAllowedLoginAttempts: $maxAllowedLoginAttempts,
            mailerSettings: $mailerSettings,
            navigationItemCollection: $navigationItemCollection,
            frontendHref: $frontendHref,
            frontendName: $frontendName,
            templateDirectory: $templateDirectory
        );
        $routeCollection->addRoute(
            route: new Route(
                path: $path,
                viewDirectory: __DIR__ . '/view/',
                viewClassPrefix: 'actra\\backend',
                viewGroup: ActraBackend::viewGroup,
                defaultFileName: login::getPath(prependPath: false),
                isDefaultForLanguage: $isDefaultForLanguage,
                defaultContentType: ContentType::createHtml(),
                language: $language,
                acceptedExtension: ContentType::HTML
            )
        );
        $childNavigation = new NavigationItemCollection();
        $childNavigation->addItem(navigationItem: users::getNavigationItem());
        $childNavigation->addItem(navigationItem: tokens::getNavigationItem());
        $childNavigation->addItem(navigationItem: visits::getNavigationItem());
        $childNavigation->addItem(navigationItem: notifications::getNavigationItem());
        $navigationItemCollection->addItem(
            navigationItem: new NavigationItem(
                navKey: 'users',
                href: users::getPath() . '?reset',
                svgPath: 'M2 22C2 17.5817 5.58172 14 10 14C14.4183 14 18 17.5817 18 22H16C16 18.6863 13.3137 16 10 16C6.68629 16 4 18.6863 4 22H2ZM10 13C6.685 13 4 10.315 4 7C4 3.685 6.685 1 10 1C13.315 1 16 3.685 16 7C16 10.315 13.315 13 10 13ZM10 11C12.21 11 14 9.21 14 7C14 4.79 12.21 3 10 3C7.79 3 6 4.79 6 7C6 9.21 7.79 11 10 11ZM18.2837 14.7028C21.0644 15.9561 23 18.752 23 22H21C21 19.564 19.5483 17.4671 17.4628 16.5271L18.2837 14.7028ZM17.5962 3.41321C19.5944 4.23703 21 6.20361 21 8.5C21 11.3702 18.8042 13.7252 16 13.9776V11.9646C17.6967 11.7222 19 10.264 19 8.5C19 7.11935 18.2016 5.92603 17.041 5.35635L17.5962 3.41321Z',
                title: 'Benutzer',
                requiredAccessRights: AccessRightCollection::createEmpty(),
                childNavigation: $childNavigation
            )
        );
    }

    public static function get(): ActraBackend
    {
        return ActraBackend::$instance;
    }
}