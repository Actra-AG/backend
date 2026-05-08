<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\rule;

use actra\backend\libs\form\component\IpWhitelistField;
use actra\yuf\datacheck\validatorTypes\IpTypeEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;
use LogicException;

class ValidIpAddressesRule extends FormRule
{
    public function __construct(HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if (!($formField instanceof IpWhitelistField)) {
            throw new LogicException(message: 'Invalid form field type: ' . get_class(object: $formField));
        }
        if ($formField->isValueEmpty()) {
            return true;
        }
        $validValues = [];
        foreach ($formField->getRawValue() as $ip) {
            $ip = trim(string: $ip);
            if ($ip === '') {
                continue;
            }
            if (!IpValidator::validate(
                input: $ip,
                ipType: IpTypeEnum::ip
            )) {
                $errorMessage = str_replace(
                    search: '[ipAddress]',
                    replace: $ip,
                    subject: $this->getErrorMessage()->render()
                );
                $this->setErrorMessage(
                    errorMessage: HtmlText::encoded(
                        textContent: $errorMessage
                    )
                );
                return false;
            }
            $validValues[] = $ip;
        }
        $formField->setValue(value: $validValues);
        return true;
    }
}