<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\ActraBackend;
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
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\Language;
use actra\yuf\core\RecordingResponseSender;
use actra\yuf\core\ResponseSender;
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

    private function createApp(string $uri, ResponseSender $responseSender, ExceptionHandler $exceptionHandler): App
    {
        $httpRequest = new HttpRequest(host: 'example.com', uri: $uri);

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
        )
            ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
            ->withSessionHandler(sessionHandler: new ArraySessionHandler(httpRequest: $httpRequest))
            ->withLogger(logger: new RecordingLogger())
            ->withExceptionHandler(exceptionHandler: $exceptionHandler);
    }

    private static function createActraBackend(App $app): ActraBackend
    {
        return ActraBackendTestInstance::create(language: $app->getLanguage(code: 'de'));
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

        $routes = $actraBackend->createRoutes();

        $this->assertSame(
            ['/backend/'],
            array_map(callback: static fn(Route $route): string => $route->path, array: $routes),
        );
        $route = array_first(array: $routes) ?? AppConfigurationTest::fail('No backend route');
        $this->assertSame($actraBackend->actraBackendSettings->language, $route->language);
        $this->assertSame('login.html', $route->defaultFileName);
        $this->assertSame('/backend/login.html', $actraBackend->getLoginPath());
    }

    public function testBackendLanguageThatIsNotTheObjectOfTheAppThrows(): void
    {
        $app = $this->createApp(
            uri: '/',
            responseSender: new RecordingResponseSender(),
            exceptionHandler: new ExceptionHandler(),
        );
        $actraBackend = ActraBackendTestInstance::create(language: new Language(code: 'de', locale: 'de_CH.UTF-8'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The language "de" of the route "/backend/" is not added: call withLanguage() first and give the route'
            . ' $app->getLanguage(code: \'de\').',
        );

        $actraBackend->configure(app: $app);
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
