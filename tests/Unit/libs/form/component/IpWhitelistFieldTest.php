<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form\component;

use actra\backend\libs\form\component\IpWhitelistField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class IpWhitelistFieldTest extends TestCase
{
    /**
     * @param list<string> $initialIpAddresses
     */
    private function createField(array $initialIpAddresses = [], ?HtmlText $requiredError = null): IpWhitelistField
    {
        return new IpWhitelistField(
            name: 'ipWhitelist',
            label: HtmlText::encoded(textContent: 'IP-Whitelist'),
            value: $initialIpAddresses,
            invalidErrorMessage: HtmlText::encoded(textContent: 'Ungültige IP-Adresse [ipAddress]'),
            requiredError: $requiredError,
            fieldInfo: HtmlText::unencoded(textContent: 'One IP address per line.'),
        );
    }

    public function testInitialIpAddressesAreRenderedOnePerLine(): void
    {
        $field = $this->createField(initialIpAddresses: ['10.0.0.1', '10.0.0.2']);

        $this->assertSame('10.0.0.1' . PHP_EOL . '10.0.0.2', $field->getValueAsString());
        $this->assertSame(['10.0.0.1', '10.0.0.2'], $field->getValues());
    }

    public function testPostedLinesAreTrimmedAndEmptyLinesIgnored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['ipWhitelist' => " 10.0.0.1 \r\n\r\n2001:db8::1\n"]));

        $this->assertTrue($isValid);
        $this->assertSame(['10.0.0.1', '2001:db8::1'], $field->getValues());
    }

    public function testEmptyInputIsAnEmptyWhitelist(): void
    {
        $field = $this->createField(initialIpAddresses: ['10.0.0.1']);

        $isValid = $field->validate(input: FormInput::fromArray(data: ['ipWhitelist' => '']));

        $this->assertTrue($isValid);
        $this->assertSame([], $field->getValues());
    }

    public function testInvalidLineFailsWithTheInvalidAddressInTheMessage(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['ipWhitelist' => "10.0.0.1\nfoo\nbar"]));

        $this->assertFalse($isValid);
        $errors = $field->errorCollection->listErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('Ungültige IP-Adresse foo', $errors[0]->render());
    }

    public function testRequiredErrorForEmptyInput(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Pflichtfeld'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['ipWhitelist' => " \n "])));
    }

    public function testValueHasNotChangedForSameAddressesInOtherOrder(): void
    {
        $field = $this->createField(initialIpAddresses: ['10.0.0.1', '10.0.0.2']);

        $field->validate(input: FormInput::fromArray(data: ['ipWhitelist' => "10.0.0.2\n\n 10.0.0.1"]));

        $this->assertFalse($field->valueHasChanged());
    }

    public function testValueHasChangedForOtherAddresses(): void
    {
        $field = $this->createField(initialIpAddresses: ['10.0.0.1']);

        $field->validate(input: FormInput::fromArray(data: ['ipWhitelist' => "10.0.0.1\n10.0.0.3"]));

        $this->assertTrue($field->valueHasChanged());
    }
}
