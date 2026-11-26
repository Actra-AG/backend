<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\settings\BackendFooterLinks;
use actra\backend\settings\FooterLink;
use actra\yuf\core\Language;

/**
 * Footer links with the language code in label and URL; keeps the languages it was asked for.
 */
final class FixedFooterLinks implements BackendFooterLinks
{
    /** @var list<string> */
    public array $requestedLanguageCodes = [];

    /**
     * @param list<string> $labels The labels of the links, each with the URL `/<language>/<position>.html`
     */
    public function __construct(private readonly array $labels) {}

    #[\Override]
    public function listFooterLinks(Language $language): array
    {
        $this->requestedLanguageCodes[] = $language->code;
        $footerLinks = [];
        foreach ($this->labels as $position => $label) {
            $footerLinks[] = new FooterLink(
                label: $label . ' ' . $language->code,
                href: '/' . $language->code . '/' . $position . '.html',
            );
        }

        return $footerLinks;
    }
}
