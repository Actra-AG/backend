<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\common;

use DateTimeInterface;
use IntlDateFormatter;
use UnexpectedValueException;

/**
 * Formats dates for the locale of a backend route (`BackendRoute::$dateFormatter`), e.g. `09.10.2026, 14:05:33` for
 * `de_CH` and `9 Oct 2026, 14:05:33` for `en_GB`. The ICU formatters are created on first use.
 */
final class DateFormatter
{
    private ?IntlDateFormatter $dateFormatter = null;
    private ?IntlDateFormatter $dateTimeFormatter = null;

    public function __construct(public readonly string $locale) {}

    public function formatDate(DateTimeInterface $dateTime): string
    {
        $this->dateFormatter ??= $this->createFormatter(timeType: IntlDateFormatter::NONE);

        return $this->format(intlDateFormatter: $this->dateFormatter, dateTime: $dateTime);
    }

    public function formatDateTime(DateTimeInterface $dateTime): string
    {
        $this->dateTimeFormatter ??= $this->createFormatter(timeType: IntlDateFormatter::MEDIUM);

        return $this->format(intlDateFormatter: $this->dateTimeFormatter, dateTime: $dateTime);
    }

    private function createFormatter(int $timeType): IntlDateFormatter
    {
        // Time zone null: the default time zone of PHP, the one of the dates read from the database
        return new IntlDateFormatter(
            locale: $this->locale,
            dateType: IntlDateFormatter::MEDIUM,
            timeType: $timeType,
            timezone: null,
        );
    }

    private function format(IntlDateFormatter $intlDateFormatter, DateTimeInterface $dateTime): string
    {
        $formatted = $intlDateFormatter->format(datetime: $dateTime);
        if ($formatted === false) {
            throw new UnexpectedValueException(
                message: 'Could not format the date: ' . $intlDateFormatter->getErrorMessage(),
            );
        }

        return $formatted;
    }
}
