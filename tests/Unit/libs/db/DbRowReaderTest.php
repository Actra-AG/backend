<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\db;

use actra\backend\libs\db\DbRowReader;
use PHPUnit\Framework\TestCase;
use stdClass;
use UnexpectedValueException;

final class DbRowReaderTest extends TestCase
{
    private function createRow(): DbRowReader
    {
        $row = new stdClass();
        $row->number = 5;
        $row->numericText = '7';
        $row->text = 'abc';
        $row->empty = null;
        $row->date = '2026-10-04 12:00:00';

        return new DbRowReader(row: $row);
    }

    public function testReadsTypedValues(): void
    {
        $row = $this->createRow();

        $this->assertSame(5, $row->getInt(column: 'number'));
        $this->assertSame(7, $row->getInt(column: 'numericText'));
        $this->assertSame('abc', $row->getString(column: 'text'));
        $this->assertFalse($row->getBool(column: 'number'));
        $this->assertSame('2026-10-04', $row->getDateTime(column: 'date')->format(format: 'Y-m-d'));
    }

    public function testNullableGettersReturnNullForNull(): void
    {
        $row = $this->createRow();

        $this->assertNull($row->getNullableString(column: 'empty'));
        $this->assertNull($row->getNullableInt(column: 'empty'));
        $this->assertNull($row->getNullableDateTime(column: 'empty'));
        $this->assertSame('', $row->getStringOrEmpty(column: 'empty'));
    }

    public function testThrowsForWrongType(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->createRow()->getInt(column: 'text');
    }

    public function testThrowsForMissingColumn(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->createRow()->getString(column: 'missing');
    }
}