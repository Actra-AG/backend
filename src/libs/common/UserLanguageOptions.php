<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use actra\backend\BackendViewContext;
use actra\backend\i18n\MessageTemplate;
use actra\backend\settings\BackendRouteCollection;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use Locale;

/**
 * The languages a backend user can choose from (the languages of the backend routes) with their display names.
 *
 * @internal
 */
final readonly class UserLanguageOptions
{
    /**
     * @param string $displayLocale The locale the language names are written in (the language of the current route)
     * @param string $defaultTemplate Text for "no language", with the placeholder `[language]` (main route language)
     */
    public function __construct(
        private BackendRouteCollection $backendRouteCollection,
        private string $displayLocale,
        private string $defaultTemplate,
    ) {}

    public static function forContext(BackendViewContext $context): UserLanguageOptions
    {
        return new UserLanguageOptions(
            backendRouteCollection: $context->actraBackend->backendRouteCollection,
            displayLocale: $context->route->language->locale,
            defaultTemplate: $context->messages->common->languageDefault,
        );
    }

    /**
     * A language is only selectable if the backend has more than one.
     */
    public function isSelectable(): bool
    {
        return count(value: $this->backendRouteCollection->listLanguageCodes()) > 1;
    }

    public function createFormOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        foreach ($this->backendRouteCollection->listLanguageCodes() as $languageCode) {
            $formOptions->addItem(
                key: $languageCode,
                htmlText: HtmlText::fromText(text: $this->getDisplayName(languageCode: $languageCode)),
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
     * The text for "no language": the user gets the language of the main route.
     */
    public function getDefaultLabel(): string
    {
        return MessageTemplate::fill(
            template: $this->defaultTemplate,
            values: [
                'language' => $this->getDisplayName(
                    languageCode: $this->backendRouteCollection->getMainRoute()->language->code,
                ),
            ],
        );
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
