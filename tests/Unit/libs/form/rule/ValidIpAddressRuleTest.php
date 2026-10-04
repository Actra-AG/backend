<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\form\rule;

use actra\backend\libs\form\rule\ValidIpAddressRule;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidIpAddressRuleTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validIpAddressProvider(): array
    {
        return [
            'IPv4' => ['192.168.1.10'],
            'IPv6' => ['2001:db8::1'],
        ];
    }

    #[DataProvider('validIpAddressProvider')]
    public function testRuleAcceptsValidIpAddress(string $ipAddress): void
    {
        $rule = new ValidIpAddressRule(errorMessage: HtmlText::encoded(textContent: 'Ungültige IP-Adresse [ipAddress]'));

        $this->assertTrue($rule->validate(value: $ipAddress));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIpAddressProvider(): array
    {
        return [
            'text' => ['localhost'],
            'IPv4 out of range' => ['256.1.1.1'],
            'IPv4 with mask' => ['10.0.0.0/8'],
        ];
    }

    #[DataProvider('invalidIpAddressProvider')]
    public function testRuleFailsForInvalidIpAddress(string $ipAddress): void
    {
        $rule = new ValidIpAddressRule(errorMessage: HtmlText::encoded(textContent: 'Ungültige IP-Adresse [ipAddress]'));

        $this->assertFalse($rule->validate(value: $ipAddress));
    }

    public function testErrorMessageNamesTheInvalidIpAddressEncoded(): void
    {
        $rule = new ValidIpAddressRule(errorMessage: HtmlText::encoded(textContent: 'Ungültige IP-Adresse [ipAddress]'));

        $rule->validate(value: '<b>1.2.3</b>');

        $this->assertSame('Ungültige IP-Adresse &lt;b&gt;1.2.3&lt;/b&gt;', $rule->getErrorMessage()->render());
    }

    public function testErrorMessageNamesTheLastInvalidIpAddress(): void
    {
        $rule = new ValidIpAddressRule(errorMessage: HtmlText::encoded(textContent: 'Ungültige IP-Adresse [ipAddress]'));

        $rule->validate(value: 'first');
        $rule->validate(value: 'second');

        $this->assertSame('Ungültige IP-Adresse second', $rule->getErrorMessage()->render());
    }
}