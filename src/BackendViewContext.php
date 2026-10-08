<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend;

use actra\yuf\core\ViewContext;

/**
 * What every view based on `BackendView` receives: the `ViewContext` of yuf and the services of the backend. Created
 * by `BackendViewFactory`; new services are added here, so the constructors of the views do not change again.
 */
final readonly class BackendViewContext
{
    public function __construct(
        public ViewContext $viewContext,
        public ActraBackend $actraBackend,
    ) {}
}
