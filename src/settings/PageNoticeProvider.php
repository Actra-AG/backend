<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\BackendViewContext;

/**
 * Lets a project show notices above the content of every backend page after login
 * (`ActraBackendSettings::$pageNoticeProvider`), e.g. a support session or a maintenance window.
 */
interface PageNoticeProvider
{
    /**
     * Called once per page after login (layout `default`, also `ConfirmationView`), after the access check of the
     * view; not on the login pages. Runs on every such page: keep it cheap (no queries a page does not need).
     *
     * @return list<PageNotice> in this order, before the notices of the view
     */
    public function listNotices(BackendViewContext $context): array;
}
