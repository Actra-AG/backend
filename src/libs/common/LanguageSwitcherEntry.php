<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

/**
 * One language of the language switcher: a link that switches to that language and leads back to the page.
 *
 * @internal
 */
final readonly class LanguageSwitcherEntry
{
    /**
     * @param string $languageCode Used for the `lang` and `hreflang` attributes
     * @param string $label The language name in its own language, e.g. 'Deutsch'
     * @param string $href The language switch with this language and the current page (without query)
     * @param bool $isCurrent Whether this is the language of the request
     */
    public function __construct(
        public string $languageCode,
        public string $label,
        public string $href,
        public bool $isCurrent,
    ) {}
}
