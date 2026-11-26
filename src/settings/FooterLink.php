<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use InvalidArgumentException;

/**
 * A link in the footer of the backend pages (e.g. imprint, privacy policy), see `BackendFooterLinks`. Label and URL
 * are plain text; the backend escapes them.
 */
final readonly class FooterLink
{
    /**
     * @param string $label The text of the link
     * @param string $href An absolute http(s) URL or a path starting with `/`
     * @throws InvalidArgumentException for an empty label or a URL that `FooterLink::isAllowedHref()` refuses
     */
    public function __construct(
        public string $label,
        public string $href,
    ) {
        if (trim(string: $label) === '') {
            throw new InvalidArgumentException(message: 'The label of a footer link must not be empty.');
        }
        if (!FooterLink::isAllowedHref(href: $href)) {
            throw new InvalidArgumentException(
                message: 'The URL of a footer link must be an http(s) URL or a path starting with "/": ' . $href,
            );
        }
    }

    /**
     * Allows an absolute http(s) URL with host or a path starting with `/` (not `//`, which browsers read as a host).
     * Refuses other schemes (`javascript:`, `data:`, `mailto:`), whitespace, control characters and backslashes
     * (browsers read `/\host` as `//host`).
     */
    public static function isAllowedHref(string $href): bool
    {
        if (preg_match(pattern: '/[\x00-\x20\x7F\\\\]/', subject: $href) === 1) {
            return false;
        }

        return preg_match(pattern: '#^(https?://[^/?\#]+|/(?!/))#i', subject: $href) === 1;
    }
}
