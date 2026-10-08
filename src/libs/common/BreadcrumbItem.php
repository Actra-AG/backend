<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

/**
 * A parent page in the breadcrumb of a view (`BackendView::getBreadcrumbParents()`): plain text title and link, both
 * escaped when the breadcrumb is rendered.
 */
final readonly class BreadcrumbItem
{
    public function __construct(
        public string $title,
        public string $href,
    ) {}
}
