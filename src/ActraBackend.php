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
use actra\yuf\auth\AuthSession;
use actra\yuf\core\ContentType;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\db\DbSettings;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\session\Session;
use LogicException;
use RuntimeException;

Autoloader::get()->addPath(
    autoloaderPath: new AutoloaderPath(
        path: __DIR__ . DIRECTORY_SEPARATOR,
        prefix: 'actra\\backend\\',
    ),
);

final class ActraBackend
{
    public const string VIEW_GROUP = 'backend';
    public const string RIGHT_BACKEND_ACCESS = 'backend_access';
    public const string RIGHT_MANAGE_USERS = 'manage_users';
    private const string NAVIGATION_SVG_PATH_USERS
        = 'M2 22C2 17.5817 5.58172 14 10 14C14.4183 14 18 17.5817 18 22H16C16 18.6863 13.3137 16 10 16'
        . 'C6.68629 16 4 18.6863 4 22H2ZM10 13C6.685 13 4 10.315 4 7C4 3.685 6.685 1 10 1'
        . 'C13.315 1 16 3.685 16 7C16 10.315 13.315 13 10 13ZM10 11C12.21 11 14 9.21 14 7C14 4.79 12.21 3 10 3'
        . 'C7.79 3 6 4.79 6 7C6 9.21 7.79 11 10 11ZM18.2837 14.7028C21.0644 15.9561 23 18.752 23 22H21'
        . 'C21 19.564 19.5483 17.4671 17.4628 16.5271L18.2837 14.7028ZM17.5962 3.41321'
        . 'C19.5944 4.23703 21 6.20361 21 8.5C21 11.3702 18.8042 13.7252 16 13.9776V11.9646'
        . 'C17.6967 11.7222 19 10.264 19 8.5C19 7.11935 18.2016 5.92603 17.041 5.35635L17.5962 3.41321Z';
    private static ?ActraBackend $instance = null;
    /** The route of the current request (the main route until a backend view activates one) */
    public private(set) BackendRoute $currentRoute;
    /** The `ViewContext` of the current request, set by `BackendView` (`null` before and outside a backend view) */
    private ?ViewContext $viewContext = null;
    private bool $hasNavigationItems = false;

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
            $routeCollection->addRoute(route: $actraBackend->createRoute(backendRoute: $backendRoute));
        }
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
    /**
     * Makes the request of a backend view current: its route (see `activateRoute()`) and its request and session, which
     * the static helpers of the backend (`MyAuthUser::get()`, the repositories, the emails) use until they are services
     * of `BackendViewContext`.
     */
    public function activateRequest(ViewContext $viewContext): void
    {
        $this->viewContext = $viewContext;
        $this->activateRoute(route: $viewContext->route);
    }

    /**
     * The `ViewContext` of the current request, `null` outside a request of a backend view (e.g. in a CLI script).
     */
    public function findViewContext(): ?ViewContext
    {
        return $this->viewContext;
    }

    /**
     * The `ViewContext` of the current request.
     *
     * @throws LogicException outside a request of a backend view (e.g. in a CLI script)
     */
    public function getViewContext(): ViewContext
    {
        return $this->viewContext ?? throw new LogicException(
            message: 'No backend request is active. This is only available in views based on BackendView.',
        );
    }

    /**
     * The `AuthSession` of the current request.
     *
     * @throws LogicException outside a backend request or without session
     */
    public function getAuthSession(): AuthSession
    {
        return $this->getViewContext()->authSession ?? throw new LogicException(
            message: 'The backend needs a session: do not disable individualSessionHandler for backend routes.',
        );
    }

    /**
     * The `Session` of the current request.
     *
     * @throws LogicException outside a backend request or without session
     */
    public function getSession(): Session
    {
        return $this->getViewContext()->session ?? throw new LogicException(
            message: 'The backend needs a session: do not disable individualSessionHandler for backend routes.',
        );
    }

    public function activateRoute(Route $route): void
    {
        $this->currentRoute = $this->backendRouteCollection->findByPath(path: $route->path)
            ?? $this->backendRouteCollection->getForLanguage(languageCode: $route->language?->code);
        if (!$this->hasNavigationItems) {
            // Once per request: yuf's NavigationItemCollection rejects a key that is added twice
            $this->addNavigationItems();
            $this->hasNavigationItems = true;
        }
    }

    /**
     * The backend route of a language (e.g. the language of a user), the main route for an unknown language.
     */
    public function getRouteForLanguage(?string $languageCode): BackendRoute
    {
        return $this->backendRouteCollection->getForLanguage(languageCode: $languageCode);
    }

    /**
     * The view factory for routes with views based on `BackendView`: `new Route(…, viewFactory:
     * ActraBackend::get()->createViewFactory())`. Views of other classes on the same route keep working.
     */
    public function createViewFactory(): BackendViewFactory
    {
        return new BackendViewFactory(actraBackend: $this);
    }

    private function createRoute(BackendRoute $backendRoute): Route
    {
        return new Route(
            path: $backendRoute->path,
            viewDirectory: __DIR__ . '/view/',
            viewClassPrefix: 'actra\\backend',
            viewGroup: ActraBackend::VIEW_GROUP,
            defaultFileName: login::getPath(prependPath: false),
            isDefaultForLanguage: $backendRoute->isDefaultForLanguage,
            defaultContentType: ContentType::createHtml(),
            language: $backendRoute->language,
            acceptedExtension: ContentType::HTML,
            viewFactory: $this->createViewFactory(),
        );
    }

    /**
     * Adds the navigation items of the backend and of the project for the current route, once per request.
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
            $htmlDataObject->addText(
                propertyName: 'src',
                text: $path,
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
