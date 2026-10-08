<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\rule;

use actra\yuf\datacheck\validatorTypes\IpTypeEnum;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\form\rule\StringRule;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;

/**
 * Checks one IP address (IPv4 or IPv6), e.g. every line of an IP whitelist with `TextAreaField::addEachRule()`.
 * The placeholder `[ipAddress]` in the error message is replaced by the (encoded) invalid address.
 */
final class ValidIpAddressRule extends StringRule
{
    public const string PLACEHOLDER_IP_ADDRESS = '[ipAddress]';

    private readonly HtmlText $errorMessageTemplate;

    public function __construct(HtmlText $errorMessage)
    {
        parent::__construct(defaultErrorMessage: $errorMessage);
        $this->errorMessageTemplate = $errorMessage;
    }

    public function validate(string $value): bool
    {
        if (IpValidator::validate(input: $value, ipType: IpTypeEnum::ip)) {
            return true;
        }
        $this->setErrorMessage(
            errorMessage: HtmlText::encoded(
                textContent: str_replace(
                    search: ValidIpAddressRule::PLACEHOLDER_IP_ADDRESS,
                    replace: HtmlEncoder::encode(value: $value),
                    subject: $this->errorMessageTemplate->render(),
                ),
            ),
        );

        return false;
    }
}
