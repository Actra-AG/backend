<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\backend\libs\form\rule\ValidIpAddressesRule;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\html\HtmlText;
use RuntimeException;

class IpWhitelistField extends TextAreaField
{
    public function __construct(
        string $name,
        HtmlText $label,
        array $value,
        HtmlText $invalidErrorMessage,
        ?HtmlText $requiredError = null
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: $value,
            requiredError: $requiredError
        );
        $this->fieldInfo = HtmlText::encoded(textContent: 'Eine IP-Adresse pro Zeile.');
        $this->addRule(formRule: new ValidIpAddressesRule(errorMessage: $invalidErrorMessage));
    }

    public function setValue($value): void
    {
        if (is_string(value: $value)) {
            $value = explode(
                separator: PHP_EOL,
                string: $value
            );
        }
        if (!is_array(value: $value)) {
            throw new RuntimeException(message: 'Value is not an array.');
        }
        parent::setValue(value: $value);
    }

    public function getValue(): array
    {
        return parent::getRawValue();
    }

    public function valueHasChanged(): bool
    {
        $value = $this->getValue();
        $originalValue = $this->getOriginalValue();
        sort(array: $value);
        sort(array: $originalValue);
        return ($value !== $originalValue);
    }
}