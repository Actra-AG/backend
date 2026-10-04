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
     * Called by `ActraBackend::init()` for the main route and again whenever a request activates a backend route.
     * Add the items with the texts and links of `$backendRoute`; an item with the same navigation key replaces the
     * previous one at the same position.
     */
    public function addNavigationItems(
        NavigationItemCollection $navigationItemCollection,
        BackendRoute $backendRoute
    ): void;
}