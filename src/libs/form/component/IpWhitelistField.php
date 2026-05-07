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
}