<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table\column;

use actra\backend\libs\common\DateFormatter;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\AbstractTableColumn;
use actra\yuf\table\TableItem;
use Override;

/**
 * A date column formatted for the locale of the backend route (`BackendRoute::$dateFormatter`); empty for `NULL`.
 */
final class LocalizedDateColumn extends AbstractTableColumn
{
    public function __construct(
        string $identifier,
        string $label,
        private readonly DateFormatter $dateFormatter,
        private readonly bool $withTime,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ) {
        parent::__construct(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
    }

    #[Override]
    protected function renderCellValue(TableItem $tableItem): string
    {
        $dateTime = $tableItem->getRow()->getNullableDateTimeImmutable(column: $this->identifier);
        if ($dateTime === null) {
            return '';
        }

        return HtmlEncoder::encode(
            value: $this->withTime
                ? $this->dateFormatter->formatDateTime(dateTime: $dateTime)
                : $this->dateFormatter->formatDate(dateTime: $dateTime),
        );
    }
}
