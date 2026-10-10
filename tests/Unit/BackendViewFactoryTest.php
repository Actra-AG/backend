<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\BackendViewFactory;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\view\factory\php\PlainView;
use actra\backend\tests\Double\view\factory\php\ProjectBackendView;
use actra\backend\tests\Double\ViewContextFactory;
use actra\backend\view\backend\php\logout;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BackendViewFactoryTest extends TestCase
{
    private function createContext(string $fileTitle): ViewContext
    {
        return ViewContextFactory::create(
            route: new Route(
                path: '/factory/',
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
        $this->actraBackend = ActraBackendTestInstance::create();
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
        $context = ViewContextFactory::create(
            route: new Route(
                path: '/backend/',
                viewDirectory: __DIR__,
                viewClassPrefix: 'actra\\backend',
                viewGroup: ActraBackend::VIEW_GROUP,
            ),
            fileTitle: 'logout',
        );

        $view = $this->createFactoryWithClosure(calls: $calls)->createView(context: $context);

        $this->assertSame([], $calls);
        $this->assertInstanceOf(logout::class, $view);
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
}
