<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\auth\UserController;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\db\DB;
use actra\backend\libs\email\Mailer;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\BackendRoute;
use actra\backend\settings\BackendRouteCollection;
use actra\backend\settings\MailerSettings;
use actra\backend\view\backend\php\notifications;
use actra\backend\view\backend\php\tokens;
use actra\backend\view\backend\php\users;
use actra\backend\view\backend\php\visits;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\ContentType;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\db\DbSettings;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use LogicException;

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
    private ?BackendRepositories $repositories = null;

    /**
     * @param string $path The path of the main route
     */
    private function __construct(
        public readonly string $path,
        public readonly ActraBackendSettings $actraBackendSettings,
        public readonly DbSettings $dbSettings,
        public readonly MailerSettings $mailerSettings,
        public readonly string $templateDirectory,
        public readonly BackendRouteCollection $backendRouteCollection,
    ) {}

    /**
     * Registers the backend under `$path` in the language of the settings (main route) and under the additional routes
     * of the settings (`ActraBackendSettings::$additionalRoutes`). Keep the instance for the routes of the project
     * views based on `BackendView` (`createViewFactory()`).
     */
    public static function init(
        RouteCollection $routeCollection,
        string $path,
        bool $isDefaultForLanguage,
        ActraBackendSettings $actraBackendSettings,
        DbSettings $dbSettings,
        MailerSettings $mailerSettings,
        ?string $templateDirectory = null,
    ): ActraBackend {
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
            templateDirectory: $templateDirectory ?? __DIR__ . '/view/backend/templates/',
            backendRouteCollection: $backendRouteCollection,
        );
        foreach ($backendRouteCollection->routes as $backendRoute) {
            $routeCollection->addRoute(route: $actraBackend->createRoute(backendRoute: $backendRoute));
        }

        return $actraBackend;
    }

    /**
     * The repositories of the backend; the database connection is opened on first use (also in CLI scripts).
     */
    public function getRepositories(): BackendRepositories
    {
        return $this->repositories ??= new BackendRepositories(
            connect: fn(): DB => DB::fromSettings(dbSettings: $this->dbSettings),
        );
    }

    /**
     * The mailer of the backend; CLI scripts pass a `NativeResponseSender`.
     */
    public function createMailer(ResponseSender $responseSender): Mailer
    {
        return new Mailer(mailerSettings: $this->mailerSettings, responseSender: $responseSender);
    }

    /**
     * The backend route of a request: the route with the same path, otherwise (e.g. a project view based on
     * `BackendView`) the backend route of its language.
     */
    public function getRouteOfRequest(Route $route): BackendRoute
    {
        return $this->backendRouteCollection->findByPath(path: $route->path)
            ?? $this->backendRouteCollection->getForLanguage(languageCode: $route->language?->code);
    }

    /**
     * The services of the backend for the request of a view based on `BackendView` (see `BackendViewFactory`).
     *
     * @throws LogicException for a route without session
     */
    public function createContext(ViewContext $viewContext): BackendViewContext
    {
        $session = $viewContext->session;
        $authSession = $viewContext->authSession;
        if ($session === null || $authSession === null) {
            throw new LogicException(
                message: 'The backend needs a session: do not disable individualSessionHandler for backend routes.',
            );
        }
        $route = $this->getRouteOfRequest(route: $viewContext->route);
        $paths = new BackendPaths(path: $route->path);
        $repositories = $this->getRepositories();
        $clientData = ClientData::fromRequest(httpRequest: $viewContext->httpRequest, authSession: $authSession);
        $currentUser = MyAuthUser::findLoggedIn(
            authSession: $authSession,
            repositories: $repositories,
            clientData: $clientData,
        );

        return new BackendViewContext(
            viewContext: $viewContext,
            actraBackend: $this,
            route: $route,
            paths: $paths,
            repositories: $repositories,
            mailer: $this->createMailer(responseSender: $viewContext->responseSender),
            session: $session,
            authSession: $authSession,
            clientData: $clientData,
            currentUser: $currentUser,
            userController: new UserController(
                repositories: $repositories,
                userDeleteHandler: $this->actraBackendSettings->userDeleteHandler,
                authSession: $authSession,
                currentUser: $currentUser,
            ),
        );
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
     * $actraBackend->createViewFactory())`. Views of other classes on the same route keep working.
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
            defaultFileName: BackendPaths::LOGIN_FILE_NAME,
            isDefaultForLanguage: $backendRoute->isDefaultForLanguage,
            defaultContentType: ContentType::createHtml(),
            language: $backendRoute->language,
            acceptedExtension: ContentType::HTML,
            viewFactory: $this->createViewFactory(),
        );
    }

    /**
     * The navigation of the backend and of the project (`ActraBackendSettings::$projectNavigation`) in the language of
     * the backend route of the request. Pass it to yuf as provider, so it is built per request:
     * `$core->prepareHttpResponse(…, navigationProvider: $actraBackend->createNavigation(...))`.
     */
    public function createNavigation(ViewContext $viewContext): NavigationItemCollection
    {
        $route = $this->getRouteOfRequest(route: $viewContext->route);
        $paths = new BackendPaths(path: $route->path);
        $messages = $route->messages;
        $childNavigation = new NavigationItemCollection();
        $childNavigation->addItem(navigationItem: users::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(navigationItem: tokens::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(navigationItem: visits::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(
            navigationItem: notifications::getNavigationItem(paths: $paths, messages: $messages),
        );
        // Project items first, the users item of the backend last (order since v2.2)
        $navigationItemCollection = new NavigationItemCollection();
        $this->actraBackendSettings->projectNavigation?->addNavigationItems(
            navigationItemCollection: $navigationItemCollection,
            backendRoute: $route,
        );
        $navigationItemCollection->addItem(navigationItem: new NavigationItem(
            navKey: 'users',
            href: $paths->users() . '?reset',
            svgPath: ActraBackend::NAVIGATION_SVG_PATH_USERS,
            title: $messages->layout->navigationTitleUsers,
            requiredAccessRights: AccessRightCollection::createEmpty(),
            childNavigation: $childNavigation,
        ));

        return $navigationItemCollection;
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
