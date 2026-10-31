<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LanguageSwitch;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use Locale;

/**
 * Builds the entries of the language switcher in the page header: links to the language switch of the backend
 * (`language.html`, yuf's `LanguageSwitch` parameters) that lead back to the current page.
 *
 * @internal
 */
final readonly class LanguageSwitcher
{
    /**
     * @param string $switchPath The page of the language switch, with the CSRF token of the session for a logged-in
     *                           user (it saves the language in the profile only with a valid token)
     * @param string $currentUri The URI of the current request; its query is dropped, because backend pages can
     *                           trigger actions with GET parameters
     */
    public function __construct(
        private LanguageCollection $languages,
        private Language $currentLanguage,
        private string $switchPath,
        private string $currentUri,
    ) {}

    /**
     * A language can only be switched if the application has more than one.
     */
    public function isAvailable(): bool
    {
        return $this->languages->isMultiLang();
    }

    /**
     * @return list<LanguageSwitcherEntry> One entry per language, in the order of the languages
     */
    public function createEntries(): array
    {
        $uriWithoutQuery = explode(separator: '?', string: $this->currentUri, limit: 2)[0];
        $entries = [];
        foreach ($this->languages->languages as $language) {
            $languageCode = $language->code;
            $entries[] = new LanguageSwitcherEntry(
                languageCode: $languageCode,
                label: $this->getLanguageName(languageCode: $languageCode),
                href: LanguageSwitch::createUri(
                    switchPath: $this->switchPath,
                    languageCode: $languageCode,
                    returnUri: $uriWithoutQuery,
                ),
                isCurrent: $languageCode === $this->currentLanguage->code,
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
            $htmlDataObject->addText(
                propertyName: 'languageCode',
                text: $entry->languageCode,
            );
            $htmlDataObject->addText(
                propertyName: 'label',
                text: $entry->label,
            );
            $htmlDataObject->addText(
                propertyName: 'href',
                text: $entry->href,
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
        $name = Locale::getDisplayLanguage(locale: $languageCode, displayLocale: $languageCode);
        if ($name === false) {
            $name = $languageCode;
        }

        return mb_convert_case(string: mb_substr(string: $name, start: 0, length: 1), mode: MB_CASE_TITLE)
            . mb_substr(string: $name, start: 1);
    }
}
