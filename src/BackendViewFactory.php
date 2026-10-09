<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\yuf\core\BaseView;
use actra\yuf\core\ClassNameViewFactory;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewFactory;

/**
 * Creates the view of a request like yuf's `ClassNameViewFactory` (class name from the route and the file name), but
 * passes a `BackendViewContext` to views based on `BackendView`. Other views get the `ViewContext` as before, so a
 * project route can mix both. Get it with `ActraBackend::createViewFactory()`.
 */
final readonly class BackendViewFactory implements ViewFactory
{
    private ClassNameViewFactory $classNameViewFactory;

    public function __construct(ActraBackend $actraBackend)
    {
        $this->classNameViewFactory = new ClassNameViewFactory(
            create: static fn(string $className, ViewContext $context): BaseView => is_subclass_of(
                object_or_class: $className,
                class: BackendView::class,
            )
                ? new $className(context: $actraBackend->createContext(viewContext: $context))
                : new $className(context: $context),
        );
    }

    #[\Override]
    public function createView(ViewContext $context): ?BaseView
    {
        return $this->classNameViewFactory->createView(context: $context);
    }
}
