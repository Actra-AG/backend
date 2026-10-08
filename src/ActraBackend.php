<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\autoloader\Autoloader;
use actra\autoloader\AutoloaderPath;
use actra\backend\i18n\BackendMessages;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\BackendRoute;
use actra\backend\settings\BackendRouteCollection;
use actra\backend\settings\MailerSettings;
use actra\backend\view\backend\php\login;
use actra\backend\view\backend\php\notifications;
use actra\backend\view\backend\php\tokens;
use actra\backend\view\backend\php\users;
use actra\backend\view\backend\php\visits;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\ContentType;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\db\DbSettings;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use LogicException;
use RuntimeException;

Autoloader::get()->addPath(
    autoloaderPath: new AutoloaderPath(
        path: __DIR__ . DIRECTORY_SEPARATOR,
        prefix: 'actra\\backend\\',
    ),
);

class ActraBackend
{
    public const string viewGroup = 'backend';
    public const string RIGHT_BACKEND_ACCESS = 'backend_access';
    public const string RIGHT_MANAGE_USERS = 'manage_users';
    private const string NAVIGATION_SVG_PATH_USERS = 'M2 22C2 17.5817 5.58172 14 10 14C14.4183 14 18 17.5817 18 22H16C16 18.6863 13.3137 16 10 16C6.68629 16 4 18.6863 4 22H2ZM10 13C6.685 13 4 10.315 4 7C4 3.685 6.685 1 10 1C13.315 1 16 3.685 16 7C16 10.315 13.315 13 10 13ZM10 11C12.21 11 14 9.21 14 7C14 4.79 12.21 3 10 3C7.79 3 6 4.79 6 7C6 9.21 7.79 11 10 11ZM18.2837 14.7028C21.0644 15.9561 23 18.752 23 22H21C21 19.564 19.5483 17.4671 17.4628 16.5271L18.2837 14.7028ZM17.5962 3.41321C19.5944 4.23703 21 6.20361 21 8.5C21 11.3702 18.8042 13.7252 16 13.9776V11.9646C17.6967 11.7222 19 10.264 19 8.5C19 7.11935 18.2016 5.92603 17.041 5.35635L17.5962 3.41321Z';
    private static ?ActraBackend $instance = null;
    /** The route of the current request (the main route until a backend view activates one) */
    public private(set) BackendRoute $currentRoute;

    /**
     * @param string $path The path of the main route; use `ActraBackend::path()` for the path of the current route
     */
    private function __construct(
        public readonly string $path,
        public readonly ActraBackendSettings $actraBackendSettings,
        public readonly DbSettings $dbSettings,
        public readonly MailerSettings $mailerSettings,
        public readonly NavigationItemCollection $navigationItemCollection,
        public readonly string $templateDirectory,
        public readonly BackendRouteCollection $backendRouteCollection,
    ) {
        $this->currentRoute = $backendRouteCollection->getMainRoute();
    }

    /**
     * Registers the backend under `$path` in the language of the settings (main route) and under the additional routes
     * of the settings (`ActraBackendSettings::$additionalRoutes`).
     */
    public static function init(
        RouteCollection $routeCollection,
        string $path,
        bool $isDefaultForLanguage,
        ActraBackendSettings $actraBackendSettings,
        DbSettings $dbSettings,
        MailerSettings $mailerSettings,
        NavigationItemCollection $navigationItemCollection,
        ?string $templateDirectory = null,
    ): void {
        if (ActraBackend::$instance !== null) {
            throw new RuntimeException(message: 'ActraBackend is already initialized');
        }
        $backendRouteCollection = new BackendRouteCollection(
            mainRoute: new BackendRoute(
                path: $path,
                language: $actraBackendSettings->language,
                isDefaultForLanguage: $isDefaultForLanguage,
                messages: $actraBackendSettings->messages,
            ),
            additionalRoutes: $actraBackendSettings->additionalRoutes,
        );
        $actraBackend = new ActraBackend(
            path: $path,
            actraBackendSettings: $actraBackendSettings,
            dbSettings: $dbSettings,
            mailerSettings: $mailerSettings,
            navigationItemCollection: $navigationItemCollection,
            templateDirectory: $templateDirectory ?? __DIR__ . '/view/backend/templates/',
            backendRouteCollection: $backendRouteCollection,
        );
        ActraBackend::$instance = $actraBackend;
        foreach ($backendRouteCollection->routes as $backendRoute) {
            $routeCollection->addRoute(route: ActraBackend::createRoute(backendRoute: $backendRoute));
        }
        $actraBackend->addNavigationItems();
    }

    public static function get(): ActraBackend
    {
        return ActraBackend::$instance ?? throw new LogicException(
            message: 'ActraBackend is not initialized. Call ActraBackend::init() first.',
        );
    }

    /**
     * The path of the current route (e.g. '/backend/' or '/en/backend/'); all links of the backend start with it.
     */
    public static function path(): string
    {
        return ActraBackend::get()->currentRoute->path;
    }

    /**
     * Makes the backend route of the request current: its texts and path are used from now on. A route that does not
     * belong to the backend (e.g. a project view based on `BackendView`) selects the backend route of its language.
     */
    public function activateRoute(Route $route): void
    {
        $this->currentRoute = $this->backendRouteCollection->findByPath(path: $route->path)
            ?? $this->backendRouteCollection->getForLanguage(languageCode: $route->language?->code);
        $this->addNavigationItems();
    }

    /**
     * The backend route of a language (e.g. the language of a user), the main route for an unknown language.
     */
    public function getRouteForLanguage(?string $languageCode): BackendRoute
    {
        return $this->backendRouteCollection->getForLanguage(languageCode: $languageCode);
    }

    private static function createRoute(BackendRoute $backendRoute): Route
    {
        return new Route(
            path: $backendRoute->path,
            viewDirectory: __DIR__ . '/view/',
            viewClassPrefix: 'actra\\backend',
            viewGroup: ActraBackend::viewGroup,
            defaultFileName: login::getPath(prependPath: false),
            isDefaultForLanguage: $backendRoute->isDefaultForLanguage,
            defaultContentType: ContentType::createHtml(),
            language: $backendRoute->language,
            acceptedExtension: ContentType::HTML,
        );
    }

    /**
     * Adds the navigation items of the backend and of the project for the current route. Called again for another
     * route, the items replace those of the same navigation key at the same position.
     */
    private function addNavigationItems(): void
    {
        $this->navigationItemCollection->addItem(navigationItem: $this->createNavigationItem());
        $this->actraBackendSettings->projectNavigation?->addNavigationItems(
            navigationItemCollection: $this->navigationItemCollection,
            backendRoute: $this->currentRoute,
        );
    }

    /**
     * Creates the navigation of the backend for the current route.
     */
    private function createNavigationItem(): NavigationItem
    {
        $childNavigation = new NavigationItemCollection();
        $childNavigation->addItem(navigationItem: users::getNavigationItem());
        $childNavigation->addItem(navigationItem: tokens::getNavigationItem());
        $childNavigation->addItem(navigationItem: visits::getNavigationItem());
        $childNavigation->addItem(navigationItem: notifications::getNavigationItem());

        return new NavigationItem(
            navKey: 'users',
            href: users::getPath() . '?reset',
            svgPath: ActraBackend::NAVIGATION_SVG_PATH_USERS,
            title: $this->currentRoute->messages->layout->navigationTitleUsers,
            requiredAccessRights: AccessRightCollection::createEmpty(),
            childNavigation: $childNavigation,
        );
    }

    /**
     * The texts of the current route.
     */
    public static function messages(): BackendMessages
    {
        return ActraBackend::get()->currentRoute->messages;
    }

    public function renderJavaScriptPaths(): HtmlDataObjectCollection
    {
        return $this->renderPaths(paths: $this->actraBackendSettings->javaScriptPaths);
    }

    /**
     * @param list<string> $paths
     */
    private function renderPaths(array $paths): HtmlDataObjectCollection
    {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        foreach ($paths as $path) {
            $htmlDataObject = new HtmlDataObject();
            $htmlDataObject->addTextElement(
                propertyName: 'src',
                content: $path,
                isEncodedForRendering: true,
            );
            $htmlDataObjectCollection->add(htmlDataObject: $htmlDataObject);
        }
        return $htmlDataObjectCollection;
    }

    public function renderStylesPaths(): HtmlDataObjectCollection
    {
        return $this->renderPaths(paths: $this->actraBackendSettings->stylesPaths);
    }
}
