<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\table;

use actra\backend\libs\common\DateFormatter;
use actra\yuf\core\Language;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DateStyleEnum;
use actra\yuf\table\TableItem;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The date columns of the tables (yuf's `DateColumn::useLocale()`) show the same dates as the backend's
 * `DateFormatter` in the views and emails.
 */
final class DateColumnTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function formats(): iterable
    {
        foreach (['de_CH', 'en_GB', 'en_US'] as $locale) {
            yield $locale . ' date' => [$locale, false];
            yield $locale . ' date and time' => [$locale, true];
        }
    }

    #[DataProvider('formats')]
    public function testSameOutputAsTheDateFormatter(string $locale, bool $withTime): void
    {
        $value = '2026-10-09 14:05:33';
        $dateColumn = new DateColumn(identifier: 'registered', label: 'Registered');
        $dateColumn->useLocale(
            language: new Language(code: substr(string: $locale, offset: 0, length: 2), locale: $locale),
            timeStyle: $withTime ? DateStyleEnum::MEDIUM : DateStyleEnum::NONE,
        );
        $dateFormatter = new DateFormatter(locale: $locale);
        $date = new DateTimeImmutable(datetime: $value);

        $this->assertSame(
            '<td>' . ($withTime
                ? $dateFormatter->formatDateTime(dateTime: $date)
                : $dateFormatter->formatDate(dateTime: $date)) . '</td>',
            $dateColumn->renderCell(tableItem: new TableItem(dataObject: (object) ['registered' => $value])),
        );
    }
}
