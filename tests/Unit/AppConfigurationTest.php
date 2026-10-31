<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\ActraBackend;
use actra\backend\i18n\AuthMessages;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\ArraySessionHandler;
use actra\backend\tests\Double\RecordingLogger;
use actra\backend\tests\Double\view\factory\php\PlainView;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\App;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\Core;
use actra\yuf\core\BaseView;
use actra\yuf\core\CoreSettings;
use actra\yuf\core\EnvironmentSettings;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\RecordingResponseSender;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\ResponseSentException;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewMap;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\html\HtmlText;
use actra\yuf\layout\NavigationItem;
use actra\yuf\layout\NavigationItemCollection;
use FilesystemIterator;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * `ActraBackend::configure()` with yuf's `App` around a `Core` of temporary directories (no database: the requests
 * have no login).
 */
final class AppConfigurationTest extends TestCase
{
    private string $appDirectory;

    #[Override]
    protected function setUp(): void
    {
        $this->appDirectory = sys_get_temp_dir() . '/actra-backend-app-'
            . bin2hex(string: random_bytes(length: 8)) . '/';
        foreach (['cache', 'error_docs', 'logs', 'settings', 'snippets', 'view/factory/html'] as $directory) {
            mkdir(directory: $this->appDirectory . $directory, recursive: true);
        }
        file_put_contents(filename: $this->appDirectory . 'view/factory/html/plain.html', data: '<p>Plain</p>');
        file_put_contents(
            filename: $this->appDirectory . 'view/factory/html/PlainView.html',
            data: '<p>{tst:lang key=\'greeting\'}</p>',
        );
        mkdir(directory: $this->appDirectory . 'view/factory/language/en', recursive: true);
        file_put_contents(
            filename: $this->appDirectory . 'view/factory/language/en/global.lang.php',
            data: '<?php $txt[\'greeting\'] = \'Hello\';',
        );
        $_SESSION = []; // Sessions are disabled in the CLI; ArraySessionHandler starts none
    }

    #[Override]
    protected function tearDown(): void
    {
        // The exception handler that Core registered must not stay for the next test
        $handler = set_exception_handler(callback: null);
        restore_exception_handler();
        if (is_array(value: $handler) && $handler[0] instanceof ExceptionHandler) {
            restore_exception_handler();
        }
        unset($_SESSION);
        $iterator = new RecursiveIteratorIterator(
            iterator: new RecursiveDirectoryIterator(
                directory: $this->appDirectory,
                flags: FilesystemIterator::SKIP_DOTS,
            ),
            mode: RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            assert(assertion: $file instanceof SplFileInfo);
            $file->isDir() ? rmdir(directory: $file->getPathname()) : unlink(filename: $file->getPathname());
        }
        rmdir(directory: $this->appDirectory);
    }

    /**
     * @param array<string, string> $headers The headers of the request
     * @param string $viewClassPrefix The namespace of the project views
     */
    private function createApp(
        string $uri,
        ResponseSender $responseSender,
        ExceptionHandler $exceptionHandler,
        array $headers = [],
        string $viewClassPrefix = Core::APP_CLASS_PREFIX,
    ): App {
        $httpRequest = new HttpRequest(
            host: 'example.com',
            uri: $uri,
            queryString: (string) parse_url(url: $uri, component: PHP_URL_QUERY),
            headers: $headers,
        );

        return App::fromCore(
            core: new Core(
                settings: new CoreSettings(
                    environmentSettings: new EnvironmentSettings(
                        errorReporting: E_ALL,
                        timeZone: 'UTC',
                        allowedDomains: ['example.com'],
                        logEmailRecipient: '',
                        debug: false,
                        robots: 'noindex',
                    ),
                    copyrightYear: 2026,
                    documentRoot: $this->appDirectory . 'public/',
                    frameworkDirectory: __DIR__ . '/../../vendor/actra/yuf/src/',
                    baseDirectory: $this->appDirectory,
                    appDirectory: $this->appDirectory,
                    cacheDirectory: $this->appDirectory . 'cache/',
                    errorDocsDirectory: $this->appDirectory . 'error_docs/',
                    logDirectory: $this->appDirectory . 'logs/',
                    settingsDirectory: $this->appDirectory . 'settings/',
                    snippetsDirectory: $this->appDirectory . 'snippets/',
                    viewDirectory: $this->appDirectory . 'view/',
                ),
                httpRequest: $httpRequest,
                responseSender: $responseSender,
            ),
            viewClassPrefix: $viewClassPrefix,
        )
            ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
            ->withLanguage(code: 'en', locale: 'en_GB.UTF-8')
            ->withSessionHandler(sessionHandler: new ArraySessionHandler(httpRequest: $httpRequest))
            ->withLogger(logger: new RecordingLogger())
            ->withExceptionHandler(exceptionHandler: $exceptionHandler);
    }

    private static function createActraBackend(App $app): ActraBackend
    {
        return ActraBackendTestInstance::create(languages: $app->core->availableLanguages);
    }

    private static function createNavigation(string $navKey): NavigationItemCollection
    {
        $navigation = new NavigationItemCollection();
        $navigation->addItem(navigationItem: new NavigationItem(
            navKey: $navKey,
            href: '/',
            svgPath: '',
            title: HtmlText::fromText(text: $navKey),
            requiredAccessRights: AccessRightCollection::createEmpty(),
        ));

        return $navigation;
    }

    public function testConfigureAddsTheRoutesAndTheLoginPath(): void
    {
        $exceptionHandler = new ExceptionHandler();
        $httpResponse = RecordingResponseSender::capture(
            action: function (ResponseSender $responseSender) use ($exceptionHandler): void {
                $app = $this->createApp(
                    uri: '/backend/users.html',
                    responseSender: $responseSender,
                    exceptionHandler: $exceptionHandler,
                );
                try {
                    AppConfigurationTest::createActraBackend(app: $app)->configure(app: $app)->prepare();
                } catch (Throwable $throwable) {
                    // What PHP does with the exception handler that Core registered
                    $exceptionHandler->handleException(throwable: $throwable);
                }
            },
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_SEE_OTHER, $httpResponse->httpStatusCode);
        $this->assertSame(
            'https://example.com/backend/login.html?returnTo=%2Fbackend%2Fusers.html',
            $httpResponse->getHeader(key: 'Location'),
        );
    }

    public function testConfigureAddsTheNavigation(): void
    {
        $navigation = null;
        $app = $this->createApp(
            uri: '/plain.html',
            responseSender: new RecordingResponseSender(),
            exceptionHandler: new ExceptionHandler(),
        )->withHtmlRoute(
            path: '/',
            viewGroup: 'factory',
            viewFactory: new ViewMap()->add(
                fileTitle: 'plain',
                create: static function (ViewContext $context) use (&$navigation): BaseView {
                    $navigation = $context->getNavigation();

                    return new PlainView(context: $context);
                },
            ),
        );

        AppConfigurationTest::createActraBackend(app: $app)->configure(
            app: $app,
            frontendNavigation: static fn(ViewContext $context): NavigationItemCollection
                => AppConfigurationTest::createNavigation(navKey: 'frontend'),
        )->prepare();

        $this->assertInstanceOf(NavigationItemCollection::class, $navigation);
        $this->assertTrue($navigation->has(navKey: 'frontend'));
    }

    public function testCreateRoutesReturnsTheRoutesOfConfigure(): void
    {
        $actraBackend = ActraBackendTestInstance::create();

        $routes = $actraBackend->createRoutes(viewDirectory: '/app/view/');

        $this->assertSame(
            ['/backend/'],
            array_map(callback: static fn(Route $route): string => $route->path, array: $routes),
        );
        $route = array_first(array: $routes) ?? AppConfigurationTest::fail('No backend route');
        $this->assertTrue($route->usesRequestLanguage);
        $this->assertNull($route->language);
        $this->assertSame('login.html', $route->defaultFileName);
        $this->assertSame('/app/view/backend/', $route->viewDirectory);
        $this->assertSame(Core::APP_CLASS_PREFIX, $route->viewClassPrefix);
        $this->assertSame('/backend/login.html', $actraBackend->getLoginPath());
    }

    /**
     * Prepares a request of an `App` that is only a backend: the backend at "/" with the project views of the view
     * group `factory` (`tests/Double/view/factory/php/`), former backend path `/auth/`.
     */
    private function prepareBackendApp(string $uri): HttpResponse
    {
        $exceptionHandler = new ExceptionHandler();

        return RecordingResponseSender::capture(
            action: function (ResponseSender $responseSender) use ($uri, $exceptionHandler): void {
                $app = $this->createApp(
                    uri: $uri,
                    responseSender: $responseSender,
                    exceptionHandler: $exceptionHandler,
                    headers: ['Accept-Language' => 'en'],
                    viewClassPrefix: 'actra\\backend\\tests\\Double',
                );
                try {
                    ActraBackendTestInstance::create(
                        languages: $app->core->availableLanguages,
                        path: '/',
                        viewGroup: 'factory',
                    )->configure(app: $app, formerPaths: ['/auth/'])->prepare()->sendAndExit(
                        responseSender: $responseSender,
                    );
                } catch (ResponseSentException $responseSentException) {
                    throw $responseSentException;
                } catch (Throwable $throwable) {
                    $exceptionHandler->handleException(throwable: $throwable);
                }
            },
        );
    }

    public function testBackendRouteServesThePagesOfTheBackendFromTheLibrary(): void
    {
        $httpResponse = $this->prepareBackendApp(uri: '/login.html');

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertStringContainsString(
            new AuthMessages()->loginIntro,
            $httpResponse->getContentString() ?? '',
        );
    }

    public function testBackendRouteServesTheProjectViewsWithTheirLanguageFiles(): void
    {
        $httpResponse = $this->prepareBackendApp(uri: '/PlainView.html');

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertSame('<p>Hello</p>', $httpResponse->getContentString());
    }

    public function testFormerPathRedirectsToTheBackendPath(): void
    {
        $httpResponse = $this->prepareBackendApp(uri: '/auth/users.html?reset');

        $this->assertSame(HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY, $httpResponse->httpStatusCode);
        $this->assertSame('https://example.com/users.html?reset', $httpResponse->getHeader(key: 'Location'));
    }

    public function testLanguagesThatAreNotThoseOfTheAppThrow(): void
    {
        $app = $this->createApp(
            uri: '/',
            responseSender: new RecordingResponseSender(),
            exceptionHandler: new ExceptionHandler(),
        );
        $actraBackend = ActraBackendTestInstance::create(
            languages: new LanguageCollection(languages: [$app->getLanguage(code: 'de')]),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The backend needs the languages of the App: ActraBackend::create(languages: '
            . '$app->core->availableLanguages, …).',
        );

        $actraBackend->configure(app: $app);
    }

    /**
     * One path for all languages: without login and session, the browser decides (yuf's `RequestLanguageResolver`).
     */
    public function testLoginPageHasTheLanguageOfTheBrowser(): void
    {
        $app = $this->createApp(
            uri: '/backend/login.html',
            responseSender: new RecordingResponseSender(),
            exceptionHandler: new ExceptionHandler(),
            headers: ['Accept-Language' => 'en-GB,en;q=0.8'],
        );

        $httpResponse = AppConfigurationTest::createActraBackend(app: $app)->configure(app: $app)->prepare();

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertSame('en', $httpResponse->getHeader(key: 'Content-Language'));
        $this->assertStringContainsString('<html class="no-js" lang="en">', $httpResponse->getContentString() ?? '');
    }

    public function testNavigationProviderSeparatesBackendAndFrontendRoutes(): void
    {
        $actraBackend = ActraBackendTestInstance::create();
        $backendRoute = new Route(
            path: '/backend/',
            viewDirectory: __DIR__,
            viewFactory: $actraBackend->createViewFactory(),
        );
        $frontendRoute = new Route(path: '/', viewDirectory: __DIR__);
        $frontendNavigation = static fn(ViewContext $context): NavigationItemCollection
            => AppConfigurationTest::createNavigation(navKey: 'frontend');

        $provider = $actraBackend->createNavigationProvider(frontendNavigation: $frontendNavigation);
        $withoutFrontend = $actraBackend->createNavigationProvider(frontendNavigation: null);

        $backendContext = ViewContextFactory::create(route: $backendRoute, fileTitle: 'users');
        $frontendContext = ViewContextFactory::create(route: $frontendRoute, fileTitle: 'index');
        $this->assertTrue($provider($backendContext)->has(navKey: 'users'));
        $this->assertTrue($provider($frontendContext)->has(navKey: 'frontend'));
        $this->assertFalse($withoutFrontend($frontendContext)->has(navKey: 'users'));
    }
}
