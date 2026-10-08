<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\db\DB;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\CSVFile;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDB;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\renderer\TablePaginationRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;

abstract class AbstractTable extends DbResultTable
{
    protected readonly BackendViewContext $backendContext;

    /**
     * @param ?FrameworkDB $db The database of the backend (`DB::get()`) if `null`
     */
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        DbQuery $dbQuery,
        int $itemsPerPage = 25,
        ?FrameworkDB $db = null,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->backendContext = $context;
        $common = ActraBackend::messages()->common;
        parent::__construct(
            identifier: $identifier,
            db: $db ?? DB::get(),
            dbQuery: $dbQuery,
            tablePaginationRenderer: new TablePaginationRenderer(
                previousTitle: $common->paginationPrevious,
                nextTitle: $common->paginationNext,
            ),
            itemsPerPage: $itemsPerPage,
        );
    }

    public function render(): string
    {
        $this->setMessages();
        $this->fullHtml = DbResultTable::filter . SmartTable::totalAmount . DbResultTable::pagination . '<div class="table-wrap">' . SmartTable::table . '</div>' . DbResultTable::pagination;
        return parent::render();
    }

    public function export(string $name): void
    {
        $this->fillBySelectQuery();
        $headersList = [];
        $list = [];
        $i = 0;
        foreach ($this->tableItemCollection->list() as $tableItem) {
            $i++;
            $item = [];
            foreach ($tableItem->data as $key => $val) {
                if ($i === 1) {
                    $headersList[] = $key;
                }
                if (is_scalar(value: $val)) {
                    $item[] = preg_replace(
                        pattern: '/\s+/',
                        replacement: ' ',
                        subject: (string) $val,
                    );
                }
            }
            $list[] = $item;
        }
        $csvFile = new CSVFile(
            fileName: $this->clock->now()->format(format: 'Y-m-d-H-i-s') . '-' . $name . '.csv',
            headersList: $headersList,
        );
        foreach ($list as $item) {
            $csvFile->addRow(data: $item);
        }
        $csvFile->pushDownloadAndExit();
    }

    /**
     * Replaces the texts of yuf's table (German there) by the backend messages.
     */
    private function setMessages(): void
    {
        $common = ActraBackend::messages()->common;
        $this->noDataHtml = DbResultTable::filter
            . '<p class="no-entry">' . HtmlEncoder::encode(value: $common->tableNoEntries) . '</p>';
        $this->totalAmountMessage_oneResult = MessageTemplate::fill(
            template: HtmlEncoder::encode(value: $common->tableOneResult),
            values: ['count' => '<strong>1</strong>'],
        );
        $this->totalAmountMessage_numResults = MessageTemplate::fill(
            template: HtmlEncoder::encode(value: $common->tableResults),
            values: ['count' => '<strong>' . SmartTable::amount . '</strong>'],
        );
    }
}
