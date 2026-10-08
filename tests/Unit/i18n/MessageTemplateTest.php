<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\i18n;

use actra\backend\i18n\MessageTemplate;
use PHPUnit\Framework\TestCase;

final class MessageTemplateTest extends TestCase
{
    public function testFillReplacesEveryPlaceholder(): void
    {
        $text = MessageTemplate::fill(
            template: 'The code expires in [minutes] minutes, [minutes] at most. Sent to [email].',
            values: ['minutes' => '10', 'email' => 'a@example.com'],
        );

        $this->assertSame('The code expires in 10 minutes, 10 at most. Sent to a@example.com.', $text);
    }

    public function testFillDoesNotReplaceInsideInsertedValues(): void
    {
        $text = MessageTemplate::fill(template: '[a] and [b]', values: ['a' => '[b]', 'b' => 'x']);

        $this->assertSame('[b] and x', $text);
    }

    public function testFillKeepsUnknownPlaceholders(): void
    {
        $this->assertSame('Hello [name]', MessageTemplate::fill(template: 'Hello [name]', values: []));
    }

    public function testListPlaceholderNamesReturnsSortedUniqueNames(): void
    {
        $this->assertSame(
            ['email', 'minutes'],
            MessageTemplate::listPlaceholderNames(template: '[minutes] [email] [minutes] [not a placeholder]'),
        );
    }
}
