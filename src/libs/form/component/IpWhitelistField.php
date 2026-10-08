<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\form\component;

use actra\backend\ActraBackend;
use actra\backend\libs\form\rule\ValidIpAddressRule;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\html\HtmlText;

/**
 * One IP address per line. `getValues()` returns the addresses (trimmed, without empty lines).
 */
final class IpWhitelistField extends TextAreaField
{
    /** @var list<string> */
    private readonly array $initialIpAddresses;

    /**
     * @param list<string> $value The initial IP addresses
     * @param HtmlText $invalidErrorMessage `[ipAddress]` is replaced by the invalid address
     * @param ?HtmlText $fieldInfo Default: `CommonMessages::$ipWhitelistInfo` of the backend messages
     */
    public function __construct(
        string $name,
        HtmlText $label,
        array $value,
        HtmlText $invalidErrorMessage,
        ?HtmlText $requiredError = null,
        ?HtmlText $fieldInfo = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: implode(separator: PHP_EOL, array: $value),
            requiredError: $requiredError,
        );
        $this->initialIpAddresses = $this->getValues();
        $this->fieldInfo = $fieldInfo ?? HtmlText::fromText(
            text: ActraBackend::messages()->common->ipWhitelistInfo,
        );
        $this->addEachRule(formRule: new ValidIpAddressRule(errorMessage: $invalidErrorMessage));
    }

    /**
     * The order of the addresses, empty lines and surrounding whitespace do not matter.
     */
    #[\Override]
    public function valueHasChanged(): bool
    {
        $ipAddresses = $this->getValues();
        $initialIpAddresses = $this->initialIpAddresses;
        sort(array: $ipAddresses);
        sort(array: $initialIpAddresses);

        return $ipAddresses !== $initialIpAddresses;
    }
}
