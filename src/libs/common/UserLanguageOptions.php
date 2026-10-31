<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use actra\backend\BackendViewContext;
use actra\yuf\core\LanguageCollection;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use Locale;

/**
 * The languages a backend user can choose from (the languages of the backend) with their display names.
 *
 * @internal
 */
final readonly class UserLanguageOptions
{
    /**
     * @param string $displayLocale The locale the language names are written in (the language of the request)
     * @param string $defaultLabel Text for "no language": the language of the session or the browser
     */
    public function __construct(
        private LanguageCollection $languages,
        private string $displayLocale,
        private string $defaultLabel,
    ) {}

    public static function forContext(BackendViewContext $context): UserLanguageOptions
    {
        return new UserLanguageOptions(
            languages: $context->actraBackend->languages,
            displayLocale: $context->language->locale,
            defaultLabel: $context->messages->common->languageDefault,
        );
    }

    /**
     * A language is only selectable if the backend has more than one.
     */
    public function isSelectable(): bool
    {
        return $this->languages->isMultiLang();
    }

    public function createFormOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($this->languages->languages as $language) {
            $formOptions->addItem(
                key: $language->code,
                htmlText: HtmlText::fromText(text: $this->getDisplayName(languageCode: $language->code)),
            );
        }

        return $formOptions;
    }

    public function getDisplayName(string $languageCode): string
    {
        // false only for an invalid locale; the code is the best remaining label
        $name = Locale::getDisplayLanguage(locale: $languageCode, displayLocale: $this->displayLocale);
        if ($name === false) {
            $name = $languageCode;
        }

        return mb_convert_case(string: mb_substr(string: $name, start: 0, length: 1), mode: MB_CASE_TITLE)
            . mb_substr(string: $name, start: 1);
    }

    /**
     * The text for "no language": the user gets the language of the session or the browser.
     */
    public function getDefaultLabel(): string
    {
        return $this->defaultLabel;
    }

    /**
     * The language of a user as text: its display name, or the default text if the user has no language.
     */
    public function render(?string $languageCode): string
    {
        return $languageCode === null
            ? $this->getDefaultLabel()
            : $this->getDisplayName(languageCode: $languageCode);
    }
}
