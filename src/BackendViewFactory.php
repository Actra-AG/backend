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
use LogicException;

/**
 * Creates the view of a request like yuf's `ClassNameViewFactory` (class name from the route and the file name), but
 * passes a `BackendViewContext` to views based on `BackendView`. Other views get the `ViewContext` as before, so a
 * project route can mix both. Get it with `ActraBackend::createViewFactory()`.
 */
final readonly class BackendViewFactory implements ViewFactory
{
    public function __construct(
        private ActraBackend $actraBackend,
        private ClassNameViewFactory $classNameViewFactory = new ClassNameViewFactory(),
    ) {}

    #[\Override]
    public function createView(ViewContext $context): ?BaseView
    {
        $className = $this->classNameViewFactory->createClassName(context: $context);
        if (!class_exists(class: $className)) {
            return null;
        }
        if (is_subclass_of(object_or_class: $className, class: BackendView::class)) {
            return new $className(context: $this->actraBackend->createContext(viewContext: $context));
        }
        if (!is_subclass_of(object_or_class: $className, class: BaseView::class)) {
            throw new LogicException(message: 'The class ' . $className . ' must extend ' . BaseView::class . '.');
        }

        return new $className(context: $context);
    }
}
