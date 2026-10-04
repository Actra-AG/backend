<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use actra\backend\settings\BackendRoute;
use actra\backend\settings\BackendRouteCollection;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use Locale;

/**
 * Builds the entries of the language switcher in the page header: the current page under every backend route.
 */
final readonly class LanguageSwitcher
{
    /**
     * @param string $currentUri The URI of the current request; its query is dropped, because backend pages can
     *                           trigger actions with GET parameters
     */
    public function __construct(
        private BackendRouteCollection $backendRouteCollection,
        private BackendRoute $currentRoute,
        private string $currentUri
    ) {
    }

    /**
     * A language can only be switched if the backend has more than one.
     */
    public function isAvailable(): bool
    {
        return count(value: $this->backendRouteCollection->routes) > 1;
    }

    /**
     * @return list<LanguageSwitcherEntry> One entry per route, in the order of the routes
     */
    public function createEntries(): array
    {
        $uriWithoutQuery = explode(separator: '?', string: $this->currentUri, limit: 2)[0];
        $entries = [];
        foreach ($this->backendRouteCollection->routes as $route) {
            $languageCode = $route->language->code;
            $entries[] = new LanguageSwitcherEntry(
                languageCode: $languageCode,
                label: $this->getLanguageName(languageCode: $languageCode),
                href: $this->backendRouteCollection->translatePath(uri: $uriWithoutQuery, targetRoute: $route),
                isCurrent: $route->path === $this->currentRoute->path
            );
        }

        return $entries;
    }

    /**
     * The entries for the template: `languageCode`, `label`, `href` (encoded) and the boolean `isCurrent`.
     */
    public function render(): HtmlDataObjectCollection
    {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        foreach ($this->createEntries() as $entry) {
            $htmlDataObject = new HtmlDataObject();
            $htmlDataObject->addTextElement(
                propertyName: 'languageCode',
                content: $entry->languageCode,
                isEncodedForRendering: false
            );
            $htmlDataObject->addTextElement(
                propertyName: 'label',
                content: $entry->label,
                isEncodedForRendering: false
            );
            $htmlDataObject->addTextElement(
                propertyName: 'href',
                content: $entry->href,
                isEncodedForRendering: false
            );
            $htmlDataObject->addBooleanValue(propertyName: 'isCurrent', booleanValue: $entry->isCurrent);
            $htmlDataObjectCollection->add(htmlDataObject: $htmlDataObject);
        }

        return $htmlDataObjectCollection;
    }

    /**
     * The language name in its own language with an upper case first letter (like `UserLanguageOptions`).
     */
    private function getLanguageName(string $languageCode): string
    {
        // false only for an invalid locale; the code is the best remaining label
        $name = Locale::getDisplayLanguage(locale: $languageCode, displayLocale: $languageCode) ?: $languageCode;

        return mb_convert_case(string: mb_substr(string: $name, start: 0, length: 1), mode: MB_CASE_TITLE)
            . mb_substr(string: $name, start: 1);
    }
}