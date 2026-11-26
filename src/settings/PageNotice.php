<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\html\HtmlText;

/**
 * A notice above the content of a backend page after login (`PageNoticeProvider`, `BackendView::addPageNotice()`).
 */
final readonly class PageNotice
{
    /**
     * @param HtmlText $content The text (`HtmlText::fromText()`), or trusted HTML of the project
     *     (`HtmlText::fromHtml()`, e.g. a form with CSRF token); never user data with `fromHtml()`
     * @param bool $isLiveRegion Announces the notice to screen readers (`role="status" aria-live="polite"`)
     */
    public function __construct(
        public PageNoticeLevelEnum $level,
        public HtmlText $content,
        public bool $isLiveRegion = false,
    ) {}
}
