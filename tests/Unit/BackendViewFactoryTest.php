<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit;

use actra\backend\BackendViewFactory;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\view\factory\php\PlainView;
use actra\backend\tests\Double\view\factory\php\ProjectBackendView;
use actra\backend\tests\Double\ViewContextFactory;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BackendViewFactoryTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $_SESSION = [];
    }

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

    private function createFactory(): BackendViewFactory
    {
        return new BackendViewFactory(actraBackend: ActraBackendTestInstance::get());
    }

    public function testBackendViewGetsTheBackendViewContext(): void
    {
        $context = $this->createContext(fileTitle: 'ProjectBackendView');

        $view = $this->createFactory()->createView(context: $context);

        $this->assertInstanceOf(ProjectBackendView::class, $view);
        $this->assertSame($context, $view->getBackendContext()->viewContext);
        $this->assertSame(ActraBackendTestInstance::get(), $view->getBackendContext()->actraBackend);
    }

    public function testOtherViewGetsTheViewContext(): void
    {
        $context = $this->createContext(fileTitle: 'PlainView');

        $view = $this->createFactory()->createView(context: $context);

        $this->assertInstanceOf(PlainView::class, $view);
        $this->assertSame($context, $view->getViewContext());
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
