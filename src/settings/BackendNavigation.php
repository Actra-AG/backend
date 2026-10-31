<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\core\Language;
use actra\yuf\layout\NavigationItemCollection;

/**
 * Lets a project add its own navigation items in the language of the request
 * (`ActraBackendSettings::$projectNavigation`).
 */
interface BackendNavigation
{
    /**
     * Called by `ActraBackend::createNavigation()` once per request. Add the items with the texts of `$language`;
     * every navigation key may be added only once (yuf throws otherwise).
     */
    public function addNavigationItems(NavigationItemCollection $navigationItemCollection, Language $language): void;
}
