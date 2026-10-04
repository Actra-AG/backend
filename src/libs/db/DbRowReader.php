<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use DateTimeImmutable;
use stdClass;
use UnexpectedValueException;

/**
 * Reads the columns of a database row as typed values. The database returns `mixed`; this is the one place where it
 * is narrowed.
 */
final readonly class DbRowReader
{
    public function __construct(private stdClass $row)
    {
    }

    public function getInt(string $column): int
    {
        $value = $this->row->$column ?? null;
        if (is_int(value: $value)) {
            return $value;
        }
        if (is_string(value: $value) && is_numeric(value: $value)) {
            return (int)$value;
        }

        throw $this->createException(column: $column, expectedType: 'an integer');
    }

    public function getNullableInt(string $column): ?int
    {
        return ($this->row->$column ?? null) === null ? null : $this->getInt(column: $column);
    }

    public function getString(string $column): string
    {
        $value = $this->row->$column ?? null;
        if (is_string(value: $value)) {
            return $value;
        }

        throw $this->createException(column: $column, expectedType: 'a string');
    }

    public function getNullableString(string $column): ?string
    {
        return ($this->row->$column ?? null) === null ? null : $this->getString(column: $column);
    }

    /**
     * A column that is NULL when the underlying value is missing (e.g. GROUP_CONCAT without rows) as text.
     */
    public function getStringOrEmpty(string $column): string
    {
        return $this->getNullableString(column: $column) ?? '';
    }

    public function getBool(string $column): bool
    {
        return $this->getInt(column: $column) === 1;
    }

    public function getNullableDateTime(string $column): ?DateTimeImmutable
    {
        $value = $this->getNullableString(column: $column);

        return $value === null ? null : new DateTimeImmutable(datetime: $value);
    }

    public function getDateTime(string $column): DateTimeImmutable
    {
        return new DateTimeImmutable(datetime: $this->getString(column: $column));
    }

    private function createException(string $column, string $expectedType): UnexpectedValueException
    {
        return new UnexpectedValueException(
            message: 'The database column ' . $column . ' is expected to be ' . $expectedType . '.'
        );
    }
}