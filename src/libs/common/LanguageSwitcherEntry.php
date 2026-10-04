<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

/**
 * One language of the language switcher: a link to the same page under the route of that language.
 */
final readonly class LanguageSwitcherEntry
{
    /**
     * @param string $languageCode Used for the `lang` and `hreflang` attributes
     * @param string $label The language name in its own language, e.g. 'Deutsch'
     * @param string $href The current page under the route of this language (without query)
     * @param bool $isCurrent Whether this is the language of the current route
     */
    public function __construct(
        public string $languageCode,
        public string $label,
        public string $href,
        public bool $isCurrent
    ) {
    }
}