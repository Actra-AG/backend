<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\yuf\common\CSVFile;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;
use DateTimeImmutable;

abstract class AbstractTable extends DbResultTable
{
    public function render(): string
    {
        $this->fullHtml = DbResultTable::filter . SmartTable::totalAmount . DbResultTable::pagination . '<div class="table-wrap">' . SmartTable::table . '</div>' . DbResultTable::pagination;
        return parent::render();
    }

    public function export(string $name): void
    {
        $this->fillBySelectQuery();
        $headersList = [];
        $list = [];
        $i = 0;
        foreach ($this->tableItemCollection->list() as $tableItemModel) {
            $i++;
            $item = [];
            foreach ($tableItemModel->data as $key => $val) {
                if ($i === 1) {
                    $headersList[] = $key;
                }
                if (!is_null(value: $val)) {
                    $item[] = preg_replace(
                        pattern: '/\s+/',
                        replacement: ' ',
                        subject: (string)$val
                    );
                }
            }
            $list[] = $item;
        }
        $csvFile = new CSVFile(
            fileName: new DateTimeImmutable()->format(format: 'Y-m-d-H-i-s') . '-' . $name . '.csv',
            headersList: $headersList
        );
        foreach ($list as $item) {
            $csvFile->addRow(data: $item);
        }
        $csvFile->pushDownloadAndExit();
    }
}