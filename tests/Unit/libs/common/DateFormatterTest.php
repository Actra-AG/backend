<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\common;

use actra\backend\libs\common\DateFormatter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function locales(): iterable
    {
        yield 'Swiss German' => ['de_CH', '09.10.2026', '09.10.2026, 14:05:33'];
        yield 'British English' => ['en_GB', '9 Oct 2026', '9 Oct 2026, 14:05:33'];
    }

    #[DataProvider('locales')]
    public function testFormatsForTheLocale(string $locale, string $expectedDate, string $expectedDateTime): void
    {
        $dateFormatter = new DateFormatter(locale: $locale);
        $dateTime = new DateTimeImmutable(datetime: '2026-10-09 14:05:33');

        $this->assertSame($expectedDate, $dateFormatter->formatDate(dateTime: $dateTime));
        $this->assertSame($expectedDateTime, $dateFormatter->formatDateTime(dateTime: $dateTime));
    }
}
