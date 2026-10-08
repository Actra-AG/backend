<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\layout\NavigationItemCollection;

/**
 * Lets a project add its own navigation items in the language of the current backend route
 * (`ActraBackendSettings::$projectNavigation`).
 */
interface BackendNavigationInterface
{
    /**
     * Called once per request, when a view based on `BackendView` activates its backend route. Add the items with the
     * texts and links of `$backendRoute`; every navigation key may be added only once (yuf throws otherwise).
     */
    public function addNavigationItems(
        NavigationItemCollection $navigationItemCollection,
        BackendRoute $backendRoute,
    ): void;
}
