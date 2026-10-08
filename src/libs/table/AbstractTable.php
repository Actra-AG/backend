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
use actra\yuf\common\CsvFile;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\renderer\TablePaginationRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;
use LogicException;

/**
 * Extension point: the base of the tables of the backend and of the project tables in the backend layout.
 */
abstract class AbstractTable extends DbResultTable
{
    protected readonly BackendViewContext $backendContext;

    /**
     * @param ?FrameworkDb $db The database of the backend (`DB::get()`) if `null`
     */
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        DbQuery $dbQuery,
        int $itemsPerPage = 25,
        ?FrameworkDb $db = null,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->backendContext = $context;
        $common = ActraBackend::messages()->common;
        parent::__construct(
            identifier: $identifier,
            db: $db ?? DB::get(),
            dbQuery: $dbQuery,
            templateEngine: $context->viewContext->templateEngine,
            httpRequest: $context->viewContext->httpRequest,
            session: $context->viewContext->session ?? throw new LogicException(message: 'Tables need a session.'),
            tablePaginationRenderer: new TablePaginationRenderer(
                previousTitle: $common->paginationPrevious,
                nextTitle: $common->paginationNext,
            ),
            itemsPerPage: $itemsPerPage,
        );
    }

    #[\Override]
    public function render(): string
    {
        $this->setMessages();
        $this->fullHtml = DbResultTable::FILTER
            . SmartTable::TOTAL_AMOUNT
            . DbResultTable::PAGINATION
            . '<div class="table-wrap">' . SmartTable::TABLE . '</div>'
            . DbResultTable::PAGINATION;
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
        $csvFile = new CsvFile(
            fileName: $this->clock->now()->format(format: 'Y-m-d-H-i-s') . '-' . $name . '.csv',
            headersList: $headersList,
        );
        foreach ($list as $item) {
            $csvFile->addRow(data: $item);
        }
        $csvFile->pushDownloadAndExit(httpRequest: $this->backendContext->viewContext->httpRequest);
    }

    /**
     * Replaces the texts of yuf's table (German there) by the backend messages.
     */
    private function setMessages(): void
    {
        $common = ActraBackend::messages()->common;
        $this->noDataHtml = DbResultTable::FILTER
            . '<p class="no-entry">' . HtmlEncoder::encode(value: $common->tableNoEntries) . '</p>';
        $this->totalAmountMessageOneResult = MessageTemplate::fill(
            template: HtmlEncoder::encode(value: $common->tableOneResult),
            values: ['count' => '<strong>1</strong>'],
        );
        $this->totalAmountMessageNumResults = MessageTemplate::fill(
            template: HtmlEncoder::encode(value: $common->tableResults),
            values: ['count' => '<strong>' . SmartTable::AMOUNT . '</strong>'],
        );
    }
}
