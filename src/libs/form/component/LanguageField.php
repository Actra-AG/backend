<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\backend\ActraBackend;
use actra\backend\libs\common\UserLanguageOptions;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\html\HtmlText;

/**
 * Select for the language of a backend user. The empty option means "language of the main route" (stored as NULL).
 */
final class LanguageField extends SelectOptionsField
{
    public function __construct(
        UserLanguageOptions $userLanguageOptions,
        ?string $initialValue
    ) {
        parent::__construct(
            name: 'language',
            label: HtmlText::unencoded(textContent: ActraBackend::messages()->common->languageLabel),
            formOptions: $userLanguageOptions->createFormOptions(),
            initialValue: $initialValue,
            individualEmptyValueLabel: HtmlText::unencoded(textContent: $userLanguageOptions->getDefaultLabel())
        );
    }

    /**
     * The selected language code, `null` for the default.
     */
    public function getLanguageCode(): ?string
    {
        $value = $this->getValueAsString();

        return $value === '' ? null : $value;
    }
}