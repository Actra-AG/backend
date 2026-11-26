<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\BackendViewContext;
use actra\backend\settings\PageNotice;
use actra\backend\settings\PageNoticeProvider;

/**
 * Returns the given notices and counts its calls.
 */
final class RecordingPageNoticeProvider implements PageNoticeProvider
{
    public int $calls = 0;

    /**
     * @param list<PageNotice> $pageNotices
     */
    public function __construct(private readonly array $pageNotices) {}

    #[\Override]
    public function listNotices(BackendViewContext $context): array
    {
        $this->calls++;

        return $this->pageNotices;
    }
}
