<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\backend\i18n\BackendMessages;
use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\auth\ProfileLanguageProvider;
use actra\backend\libs\auth\UserController;
use actra\backend\libs\common\DateFormatter;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\db\DB;
use actra\backend\libs\email\Mailer;
use actra\backend\libs\email\SentEmailLogger;
use actra\backend\libs\email\SentEmailSecrets;
use actra\backend\settings\ActraBackendSettings;
use actra\backend\settings\MailerSettings;
use actra\backend\view\backend\php\notifications;
use actra\backend\view\backend\php\sentEmails;
use actra\backend\view\backend\php\tokens;
use actra\backend\view\backend\php\users;
use actra\backend\view\backend\php\visits;
use actra\yuf\App;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\Core;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\Logger;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\db\DbSettings;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use Closure;
use LogicException;

final class ActraBackend
{
    public const string RIGHT_BACKEND_ACCESS = 'backend_access';
    public const string RIGHT_MANAGE_USERS = 'manage_users';
    public const string RIGHT_VIEW_SENT_EMAILS = 'view_sent_emails';
    private const string NAVIGATION_SVG_PATH_USERS
        = 'M2 22C2 17.5817 5.58172 14 10 14C14.4183 14 18 17.5817 18 22H16C16 18.6863 13.3137 16 10 16'
        . 'C6.68629 16 4 18.6863 4 22H2ZM10 13C6.685 13 4 10.315 4 7C4 3.685 6.685 1 10 1'
        . 'C13.315 1 16 3.685 16 7C16 10.315 13.315 13 10 13ZM10 11C12.21 11 14 9.21 14 7C14 4.79 12.21 3 10 3'
        . 'C7.79 3 6 4.79 6 7C6 9.21 7.79 11 10 11ZM18.2837 14.7028C21.0644 15.9561 23 18.752 23 22H21'
        . 'C21 19.564 19.5483 17.4671 17.4628 16.5271L18.2837 14.7028ZM17.5962 3.41321'
        . 'C19.5944 4.23703 21 6.20361 21 8.5C21 11.3702 18.8042 13.7252 16 13.9776V11.9646'
        . 'C17.6967 11.7222 19 10.264 19 8.5C19 7.11935 18.2016 5.92603 17.041 5.35635L17.5962 3.41321Z';
    public readonly BackendPaths $paths;
    private ?BackendRepositories $repositories = null;
    /** The secrets of the mail being sent, shared by the mailers and the log of sent emails */
    private readonly SentEmailSecrets $sentEmailSecrets;
    /** @var array<string, BackendMessages> The texts per language code, created on first use */
    private array $messages = [];

    /**
     * @param string $path The path of the backend route
     */
    private function __construct(
        public readonly string $path,
        public readonly string $viewGroup,
        public readonly string $defaultFileTitle,
        public readonly LanguageCollection $languages,
        public readonly ActraBackendSettings $actraBackendSettings,
        public readonly DbSettings $dbSettings,
        public readonly MailerSettings $mailerSettings,
        public readonly string $templateDirectory,
    ) {
        $this->paths = new BackendPaths(path: $path);
        $this->sentEmailSecrets = new SentEmailSecrets();
    }

    /**
     * Creates the backend under `$path`: one route for the pages of the backend (login, users, profile, …) and the
     * project views of `$viewGroup` (`app/view/backend/php/orders.php`), one path for all languages. A project view
     * with the name of a page of the backend replaces that page (see `BackendViewFactory`). Every request gets the
     * language of the user's profile, else of the session (language switcher), of the browser or the first language
     * (yuf's `RequestLanguageResolver`). Add the route with `configure()` (yuf's `App`) or `createRoutes()` (`Core`).
     *
     * @param string $path The path of the backend, e.g. `/` (an application that is only a backend) or `/backend/`
     * @param string $viewGroup The view group of the project views on the backend route (directory and namespace)
     * @param string $defaultFileTitle The page of the path itself (`/backend/`): the login page of the backend, or a
     *     page of the project (the login page is then `login.html`)
     * @param LanguageCollection $languages The languages of the application: `$app->core->availableLanguages` (`App`
     *     adds its languages when it runs) or `$core->availableLanguages`
     * @param MailerSettings $mailerSettings Every mail of its mailer is written to the log of sent emails (page
     *     "Sent emails", table `sent_emails`), also the mails the project sends with the mailer directly; create the
     *     backend once per mailer
     * @param ?Logger $logger Logs an entry of the log of sent emails that could not be written (`$app->getLogger()`,
     *     `$core->createFileLogger()`); `null` as explicit opt-out: PHP's `error_log()`
     */
    public static function create(
        string $path,
        LanguageCollection $languages,
        ActraBackendSettings $actraBackendSettings,
        DbSettings $dbSettings,
        MailerSettings $mailerSettings,
        ?Logger $logger,
        string $viewGroup = 'backend',
        string $defaultFileTitle = BackendPaths::LOGIN_FILE_TITLE,
        ?string $templateDirectory = null,
    ): ActraBackend {
        $actraBackend = new ActraBackend(
            path: $path,
            viewGroup: $viewGroup,
            defaultFileTitle: $defaultFileTitle,
            languages: $languages,
            actraBackendSettings: $actraBackendSettings,
            dbSettings: $dbSettings,
            mailerSettings: $mailerSettings,
            templateDirectory: $templateDirectory ?? __DIR__ . '/view/backend/templates/',
        );
        $mailerSettings->mailer->addSentMailListener(
            sentMailListener: new SentEmailLogger(
                repositories: $actraBackend->getRepositories(),
                logger: $logger,
                sentEmailSecrets: $actraBackend->sentEmailSecrets,
            ),
        );

        return $actraBackend;
    }

    /**
     * A copy of the `App` with the route of the backend (`withHtmlRoute()` with `usesRequestLanguage`, the target of
     * "/" if no other route is), the profile language of the logged-in user as language of the request
     * (`createRequestLanguageProvider()`, also for the project routes with `usesRequestLanguage`), its login page as
     * login path (visitors without login are sent there and back to the requested page afterwards) and the navigation
     * (`createNavigationProvider()`).
     *
     * @param ?Closure(ViewContext): NavigationItemCollection $frontendNavigation See `createNavigationProvider()`
     * @param (Closure(class-string<BackendView> $className, BackendViewContext $context): BackendView)|null $create
     *     Creates the project views of the backend route, see `createViewFactory()`
     * @param list<string> $formerPaths Former paths of the backend (e.g. `/auth/`), redirected permanently to the
     *     backend path (`createFormerPathRoute()`)
     *
     * @throws LogicException if the languages of the backend are not those of the `App` (`create()`) or a path is
     *     already used
     */
    public function configure(
        App $app,
        ?Closure $frontendNavigation = null,
        ?Closure $create = null,
        array $formerPaths = [],
    ): App {
        if ($this->languages !== $app->core->availableLanguages) {
            throw new LogicException(
                message: 'The backend needs the languages of the App: ActraBackend::create(languages: '
                    . '$app->core->availableLanguages, …).',
            );
        }
        $app = $app->withHtmlRoute(
            path: $this->path,
            viewGroup: $this->viewGroup,
            defaultFileTitle: $this->defaultFileTitle,
            viewFactory: $this->createViewFactory(create: $create),
            redirectDefaultFileName: false,
            usesRequestLanguage: true,
        );
        foreach ($formerPaths as $formerPath) {
            $app = $app->withRoute(route: $this->createFormerPathRoute(
                formerPath: $formerPath,
                httpRequest: $app->core->httpRequest,
                responseSender: $app->core->responseSender,
            ));
        }

        return $app
            ->withRequestLanguageProvider(requestLanguageProvider: $this->createRequestLanguageProvider())
            ->withLoginPath(loginPath: $this->getLoginPath())
            ->withNavigation(
                navigationProvider: $this->createNavigationProvider(frontendNavigation: $frontendNavigation),
            );
    }

    /**
     * The route of the backend (like `configure()`), for projects that call `Core::prepareHttpResponse()` themselves:
     * `new RouteCollection(routes: [...$projectRoutes, ...$actraBackend->createRoutes(viewDirectory:
     * $core->viewDirectory)], loginPath: $actraBackend->getLoginPath())`.
     *
     * @param string $viewDirectory The view directory of the project (`Core::$viewDirectory`)
     * @param string $viewClassPrefix The namespace of the project views
     * @param bool $isDefaultForLanguage `true`: "/" redirects to the backend (if no route with a fixed language is
     *     the default)
     * @param (Closure(class-string<BackendView> $className, BackendViewContext $context): BackendView)|null $create
     *     Creates the project views of the backend route, see `createViewFactory()`
     *
     * @return list<Route>
     */
    public function createRoutes(
        string $viewDirectory,
        string $viewClassPrefix = Core::APP_CLASS_PREFIX,
        bool $isDefaultForLanguage = false,
        ?Closure $create = null,
    ): array {
        return [
            new Route(
                path: $this->path,
                viewDirectory: $viewDirectory,
                viewClassPrefix: $viewClassPrefix,
                viewGroup: $this->viewGroup,
                defaultFileName: $this->defaultFileTitle . '.' . ContentType::HTML,
                isDefaultForLanguage: $isDefaultForLanguage,
                defaultContentType: ContentType::createHtml(),
                acceptedExtension: ContentType::HTML,
                viewFactory: $this->createViewFactory(create: $create),
                usesRequestLanguage: true,
            ),
        ];
    }

    /**
     * A route that redirects a former path of the backend permanently (301) to the backend path, with the file name
     * and the query: `/auth/users.html?reset` to `/backend/users.html?reset`. For old bookmarks and links in emails.
     */
    public function createFormerPathRoute(
        string $formerPath,
        HttpRequest $httpRequest,
        ResponseSender $responseSender,
    ): Route {
        $path = $this->path;

        return new Route(
            path: $formerPath,
            viewDirectory: __DIR__ . '/view/',
            viewCallback: static function () use ($formerPath, $path, $httpRequest, $responseSender): never {
                $query = $httpRequest->getQuery();
                HttpResponse::redirectAndExit(
                    relativeOrAbsoluteUri: $path . substr(string: $httpRequest->getPath(), offset: strlen(
                        string: $formerPath,
                    )) . ($query === '' ? '' : '?' . $query),
                    httpRequest: $httpRequest,
                    httpStatusCode: HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY,
                    responseSender: $responseSender,
                );
            },
            defaultContentType: ContentType::createHtml(),
        );
    }

    /**
     * The language of the request is the profile language of the logged-in user (yuf's `RequestLanguageProvider`), for
     * `Core::prepareHttpResponse(requestLanguageProvider:)`; `configure()` adds it to the `App`.
     */
    public function createRequestLanguageProvider(): RequestLanguageProvider
    {
        return new ProfileLanguageProvider(repositories: $this->getRepositories());
    }

    /**
     * The login page of the backend (`/backend/login.html`), the login path of yuf's `App` and `RouteCollection`.
     */
    public function getLoginPath(): string
    {
        return $this->paths->login();
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
        return new Mailer(
            mailerSettings: $this->mailerSettings,
            responseSender: $responseSender,
            sentEmailSecrets: $this->sentEmailSecrets,
        );
    }

    /**
     * The texts of the backend in a language (`ActraBackendSettings::$messages`, else
     * `BackendMessages::forLanguageCode()`), e.g. for an email to a user in the user's language.
     */
    public function getMessages(string $languageCode): BackendMessages
    {
        return $this->messages[$languageCode] ??= $this->actraBackendSettings->messages[$languageCode]
            ?? BackendMessages::forLanguageCode(languageCode: $languageCode);
    }

    /**
     * The language of a request: that of the route or of the request (`usesRequestLanguage`), else the first language
     * of the backend (a route without language).
     *
     * @throws LogicException if the application has no language
     */
    public function getLanguageOfRequest(ViewContext $viewContext): Language
    {
        if ($viewContext->locale->language !== null) {
            return $viewContext->locale->language;
        }
        if ($this->languages->isEmpty()) {
            throw new LogicException(message: 'The backend needs a language: add one with App::withLanguage().');
        }

        return $this->languages->getFirstLanguage();
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
        $language = $this->getLanguageOfRequest(viewContext: $viewContext);
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
            language: $language,
            messages: $this->getMessages(languageCode: $language->code),
            dateFormatter: new DateFormatter(locale: $language->locale),
            paths: $this->paths,
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
     * The user of the API key sent as bearer token (`Authorization: Bearer api_key_<public-id>_<secret>`). The key must
     * be valid, the API on (`ActraBackendSettings::$hasApi`), the user active with at least one right, and the request
     * must come from an address of the user's IP whitelist (a user without whitelist is refused). Check the rights of
     * the endpoint with the returned user (`hasOneOfRights()`).
     *
     * @throws UnauthorizedException if one of the checks fails
     */
    public function authenticateBearerOrThrow(HttpRequest $httpRequest): MyAuthUser
    {
        $bearer = $httpRequest->getBearerToken();
        if (!$this->actraBackendSettings->hasApi || $bearer === null) {
            throw new UnauthorizedException();
        }
        $repositories = $this->getRepositories();
        $dbAuthApiKey = $repositories->apiKeys()->findByBearer(bearer: $bearer);
        if ($dbAuthApiKey === null) {
            throw new UnauthorizedException();
        }
        $dbAuthUser = $repositories->users()->selectById(id: $dbAuthApiKey->userId);
        if ($dbAuthUser === null) {
            throw new UnauthorizedException();
        }
        $remoteAddress = $httpRequest->getRemoteAddress();
        $myAuthUser = new MyAuthUser(
            dbAuthUser: $dbAuthUser,
            parentSessionId: null,
            repositories: $repositories,
            clientData: new ClientData(
                userAgent: $httpRequest->getUserAgent(),
                ipAddress: $remoteAddress,
                sessionId: '',
            ),
        );
        if (
            !$myAuthUser->isActive
            || $dbAuthUser->ipWhitelist === []
            || !IpValidator::isInWhitelist(whiteList: $dbAuthUser->ipWhitelist, ipAddressToCheck: $remoteAddress)
        ) {
            throw new UnauthorizedException();
        }

        return $myAuthUser;
    }

    /**
     * The view factory of the backend route (`configure()`, `createRoutes()`) and of further project routes with views
     * based on `BackendView`: `new Route(…, viewFactory: $actraBackend->createViewFactory())`. Views of other classes
     * on the same route keep working. Only the backend route serves the pages of the backend itself.
     *
     * @param (Closure(class-string<BackendView> $className, BackendViewContext $context): BackendView)|null $create
     *     Creates the project views based on `BackendView` with further dependencies (repositories, mailers of the
     *     project); `null` creates them with `new $className(context: $context)`. The views of the backend itself are
     *     always created without it.
     */
    public function createViewFactory(?Closure $create = null): BackendViewFactory
    {
        return new BackendViewFactory(actraBackend: $this, create: $create);
    }

    /**
     * The navigation provider of yuf (one per application): routes with the view factory of the backend
     * (`createViewFactory()`, also the backend route itself) get the navigation of the backend
     * (`createNavigation()`), the other routes that of the project.
     *
     * @param ?Closure(ViewContext): NavigationItemCollection $frontendNavigation The navigation of the other routes of
     *     the project; `null`: they get an empty navigation
     *
     * @return Closure(ViewContext): NavigationItemCollection
     */
    public function createNavigationProvider(?Closure $frontendNavigation = null): Closure
    {
        return function (ViewContext $viewContext) use ($frontendNavigation): NavigationItemCollection {
            if ($viewContext->route->viewFactory instanceof BackendViewFactory) {
                return $this->createNavigation(viewContext: $viewContext);
            }

            return $frontendNavigation === null ? new NavigationItemCollection() : $frontendNavigation($viewContext);
        };
    }

    /**
     * The navigation of the backend and of the project (`ActraBackendSettings::$projectNavigation`) in the language of
     * the request, for the routes of the backend (see `createNavigationProvider()`).
     */
    public function createNavigation(ViewContext $viewContext): NavigationItemCollection
    {
        $language = $this->getLanguageOfRequest(viewContext: $viewContext);
        $paths = $this->paths;
        $messages = $this->getMessages(languageCode: $language->code);
        $childNavigation = new NavigationItemCollection();
        $childNavigation->addItem(navigationItem: users::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(navigationItem: tokens::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(navigationItem: visits::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(navigationItem: sentEmails::getNavigationItem(paths: $paths, messages: $messages));
        $childNavigation->addItem(
            navigationItem: notifications::getNavigationItem(paths: $paths, messages: $messages),
        );
        // Project items first, the users item of the backend last (order since v2.2)
        $navigationItemCollection = new NavigationItemCollection();
        $this->actraBackendSettings->projectNavigation?->addNavigationItems(
            navigationItemCollection: $navigationItemCollection,
            language: $language,
        );
        $navigationItemCollection->addItem(navigationItem: new NavigationItem(
            navKey: 'users',
            href: $paths->users() . '?reset',
            svgPath: ActraBackend::NAVIGATION_SVG_PATH_USERS,
            title: HtmlText::fromText(text: $messages->layout->navigationTitleUsers),
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
