<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use actra\yuf\html\HtmlEncoder;

/**
 * The parent pages of a view, from the top level down to the direct parent of the current page.
 */
final readonly class BreadcrumbItemCollection
{
    /** @var list<BreadcrumbItem> */
    public array $items;

    public function __construct(BreadcrumbItem ...$items)
    {
        $this->items = array_values(array: $items);
    }

    /**
     * The breadcrumb as HTML: the parents as links, the current page (already HTML) as `<strong>`.
     */
    public function render(string $currentTitleHtml, string $separator): string
    {
        $parts = [];
        foreach ($this->items as $item) {
            $parts[] = '<a href="' . HtmlEncoder::encode(value: $item->href) . '">'
                . HtmlEncoder::encode(value: $item->title) . '</a>';
        }
        $parts[] = '<strong>' . $currentTitleHtml . '</strong>';

        return '<p class="breadcrumb">' . implode(separator: $separator, array: $parts) . '</p>';
    }
}
