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
use Closure;

/**
 * Creates the view of a request like yuf's `ClassNameViewFactory` (class name from the route and the file name), but
 * passes a `BackendViewContext` to views based on `BackendView`. Other views get the `ViewContext` as before, so a
 * project route can mix both. On the backend route (`ActraBackend::$path`), a file title without project view is a page
 * of the backend itself (`actra\backend\view\backend\php\<file title>`): a project view with the same name replaces the
 * page of the backend. Get it with `ActraBackend::createViewFactory()`.
 */
final readonly class BackendViewFactory implements ViewFactory
{
    /** The namespace of the views of the backend itself; they are never created by the `create` closure. */
    public const string BACKEND_VIEW_NAMESPACE = 'actra\\backend\\view\\backend\\php\\';
    private ClassNameViewFactory $classNameViewFactory;

    /**
     * @param (Closure(class-string<BackendView> $className, BackendViewContext $context): BackendView)|null $create
     *     Creates the project views based on `BackendView` with further dependencies (`null`: `new $className(context:
     *     $context)`)
     */
    public function __construct(private ActraBackend $actraBackend, ?Closure $create = null)
    {
        $this->classNameViewFactory = new ClassNameViewFactory(
            create: static function (string $className, ViewContext $context) use ($actraBackend, $create): BaseView {
                if (!is_subclass_of(object_or_class: $className, class: BackendView::class)) {
                    return new $className(context: $context);
                }
                $backendContext = $actraBackend->createContext(viewContext: $context);
                if (
                    $create === null
                    || str_starts_with(haystack: $className, needle: BackendViewFactory::BACKEND_VIEW_NAMESPACE)
                ) {
                    return new $className(context: $backendContext);
                }

                return $create($className, $backendContext);
            },
        );
    }

    #[\Override]
    public function createView(ViewContext $context): ?BaseView
    {
        $projectView = $this->classNameViewFactory->createView(context: $context);
        if ($projectView !== null || $context->route->path !== $this->actraBackend->path) {
            return $projectView;
        }
        // The file title comes from the request: only a plain name can be a class name of the backend
        if (
            $context->fileGroup !== null
            || preg_match(pattern: '/^[a-zA-Z][a-zA-Z0-9]*$/', subject: $context->fileTitle) !== 1
        ) {
            return null;
        }
        $className = BackendViewFactory::BACKEND_VIEW_NAMESPACE . $context->fileTitle;
        if (!is_subclass_of(object_or_class: $className, class: BackendView::class)) {
            return null;
        }

        return new $className(context: $this->actraBackend->createContext(viewContext: $context));
    }
}
