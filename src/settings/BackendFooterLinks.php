<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\core\Language;

/**
 * Lets a project add further links to the footer of the backend pages (imprint, privacy policy) in the language of the
 * request (`ActraBackendSettings::$footerLinks`).
 */
interface BackendFooterLinks
{
    /**
     * Called once per rendered page with the language of the request.
     *
     * @return list<FooterLink> in this order, after the link to the website
     */
    public function listFooterLinks(Language $language): array;
}
