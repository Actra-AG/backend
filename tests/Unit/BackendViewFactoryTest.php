<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\ActraBackend;
use actra\backend\BackendPaths;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\BackendViewFactory;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\view\factory\php\logout as ProjectLogout;
use actra\backend\tests\Double\view\factory\php\PlainView;
use actra\backend\tests\Double\view\factory\php\ProjectBackendView;
use actra\backend\tests\Double\ViewContextFactory;
use actra\backend\view\backend\php\login;
use actra\yuf\auth\UnauthorizedAccessRightException;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\exception\NotFoundException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BackendViewFactoryTest extends TestCase
{
    /**
     * A request on the backend route `/factory/` (project views of `tests/Double/view/factory/php/`), or on another
     * route of the project.
     */
    private function createContext(string $fileTitle, string $path = '/factory/'): ViewContext
    {
        return ViewContextFactory::create(
            route: new Route(
                path: $path,
                viewDirectory: __DIR__,
                viewClassPrefix: 'actra\\backend\\tests\\Double',
                viewGroup: 'factory',
            ),
            fileTitle: $fileTitle,
        );
    }

    private ActraBackend $actraBackend;

    #[\Override]
    protected function setUp(): void
    {
        $this->actraBackend = ActraBackendTestInstance::create(path: '/factory/', viewGroup: 'factory');
    }

    private function createFactory(): BackendViewFactory
    {
        return new BackendViewFactory(actraBackend: $this->actraBackend);
    }

    /**
     * A factory whose `create` closure records its arguments and creates the view itself.
     *
     * @param list<array{string, BackendViewContext}> $calls
     */
    private function createFactoryWithClosure(array &$calls): BackendViewFactory
    {
        return $this->actraBackend->createViewFactory(
            create: static function (string $className, BackendViewContext $context) use (&$calls): BackendView {
                $calls[] = [$className, $context];

                return new $className(context: $context);
            },
        );
    }

    public function testBackendViewGetsTheBackendViewContext(): void
    {
        $context = $this->createContext(fileTitle: 'ProjectBackendView');

        $view = $this->createFactory()->createView(context: $context);

        $this->assertInstanceOf(ProjectBackendView::class, $view);
        $this->assertSame($context, $view->getBackendContext()->viewContext);
        $this->assertSame($this->actraBackend, $view->getBackendContext()->actraBackend);
    }

    public function testOtherViewGetsTheViewContext(): void
    {
        $context = $this->createContext(fileTitle: 'PlainView');

        $view = $this->createFactory()->createView(context: $context);

        $this->assertInstanceOf(PlainView::class, $view);
        $this->assertSame($context, $view->getViewContext());
    }

    public function testCreateClosureReceivesClassNameAndContextAndItsViewIsUsed(): void
    {
        $calls = [];
        $context = $this->createContext(fileTitle: 'ProjectBackendView');

        $view = $this->createFactoryWithClosure(calls: $calls)->createView(context: $context);

        $this->assertCount(1, $calls);
        [$className, $backendContext] = $calls[0];
        $this->assertSame(ProjectBackendView::class, $className);
        $this->assertSame($context, $backendContext->viewContext);
        $this->assertInstanceOf(ProjectBackendView::class, $view);
        $this->assertSame($backendContext, $view->getBackendContext());
    }

    public function testCreateClosureIsNotUsedForOtherViews(): void
    {
        $calls = [];
        $context = $this->createContext(fileTitle: 'PlainView');

        $view = $this->createFactoryWithClosure(calls: $calls)->createView(context: $context);

        $this->assertSame([], $calls);
        $this->assertInstanceOf(PlainView::class, $view);
    }

    public function testViewsOfTheBackendIgnoreTheCreateClosure(): void
    {
        $calls = [];

        $view = $this->createFactoryWithClosure(calls: $calls)->createView(
            context: $this->createContext(fileTitle: 'login'),
        );

        $this->assertSame([], $calls);
        $this->assertInstanceOf(login::class, $view);
    }

    public function testBackendRouteServesThePagesOfTheBackend(): void
    {
        $context = $this->createContext(fileTitle: 'login');

        $view = $this->createFactory()->createView(context: $context);

        $this->assertInstanceOf(login::class, $view);
    }

    public function testProjectViewReplacesThePageOfTheBackend(): void
    {
        $view = $this->createFactory()->createView(context: $this->createContext(fileTitle: 'logout'));

        $this->assertInstanceOf(ProjectLogout::class, $view);
    }

    public function testOtherRoutesDoNotServeThePagesOfTheBackend(): void
    {
        $this->assertNull(
            $this->createFactory()->createView(context: $this->createContext(fileTitle: 'login', path: '/other/')),
        );
    }

    public function testFileTitleThatIsNoClassNameHasNoPageOfTheBackend(): void
    {
        $this->assertNull($this->createFactory()->createView(context: $this->createContext(fileTitle: 'log-in')));
        $this->assertNull($this->createFactory()->createView(context: $this->createContext(fileTitle: 'BackendView')));
    }

    public function testFileWithoutClassHasNoView(): void
    {
        $this->assertNull($this->createFactory()->createView(context: $this->createContext(fileTitle: 'missing')));
    }

    public function testClassThatIsNoViewThrows(): void
    {
        $this->expectException(LogicException::class);

        $this->createFactory()->createView(context: $this->createContext(fileTitle: 'NotAView'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function passwordPages(): iterable
    {
        foreach (BackendPaths::PASSWORD_FILE_TITLES as $fileTitle) {
            yield $fileTitle => [$fileTitle];
        }
    }

    #[DataProvider('passwordPages')]
    public function testPasswordPageExistsWithPasswordLogin(string $fileTitle): void
    {
        $this->assertTrue(is_subclass_of(
            object_or_class: BackendViewFactory::BACKEND_VIEW_NAMESPACE . $fileTitle,
            class: BackendView::class,
        ));
        try {
            $view = $this->createFactory()->createView(context: $this->createContext(fileTitle: $fileTitle));
        } catch (UnauthorizedAccessRightException) {
            // The pages of the profile exist and need a login
            return;
        }

        $this->assertInstanceOf(BackendView::class, $view);
    }

    /**
     * Without password login the page answers 404 before a view is created (so no form of it is processed).
     */
    #[DataProvider('passwordPages')]
    public function testPasswordPageIsNotFoundWithoutPasswordLogin(string $fileTitle): void
    {
        $factory = ActraBackendTestInstance::create(path: '/factory/', viewGroup: 'factory', passwordLogin: false)
            ->createViewFactory();

        $this->expectException(NotFoundException::class);
        $factory->createView(context: $this->createContext(fileTitle: $fileTitle));
    }

    public function testOtherPagesExistWithoutPasswordLogin(): void
    {
        $factory = ActraBackendTestInstance::create(path: '/factory/', viewGroup: 'factory', passwordLogin: false)
            ->createViewFactory();

        $this->assertInstanceOf(login::class, $factory->createView(context: $this->createContext(fileTitle: 'login')));
    }
}
