<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\BackendViewContext;
use actra\yuf\db\DbQuery;
use actra\yuf\db\FrameworkDb;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DateStyleEnum;
use actra\yuf\table\renderer\TablePaginationRenderer;
use actra\yuf\table\table\DbResultTable;
use actra\yuf\table\table\SmartTable;
use LogicException;

/**
 * Extension point: the base of the tables of the backend and of the project tables in the backend layout. Export a
 * table with yuf's `exportCsv()`.
 */
abstract class AbstractTable extends DbResultTable
{
    protected readonly BackendViewContext $backendContext;

    /**
     * @param ?FrameworkDb $db The database of the backend (`$this->backendContext->repositories->db()`) if `null`
     */
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        DbQuery $dbQuery,
        int $itemsPerPage = 25,
        ?FrameworkDb $db = null,
    ) {
        $this->backendContext = $context;
        $common = $this->backendContext->messages->common;
        parent::__construct(
            identifier: $identifier,
            db: $db ?? $this->backendContext->repositories->db(),
            dbQuery: $dbQuery,
            templateEngine: $context->viewContext->templateEngine,
            httpRequest: $context->viewContext->httpRequest,
            session: $context->viewContext->session ?? throw new LogicException(message: 'Tables need a session.'),
            tablePaginationRenderer: new TablePaginationRenderer(
                previousTitle: $common->paginationPrevious,
                nextTitle: $common->paginationNext,
            ),
            itemsPerPage: $itemsPerPage,
            messages: $context->messages->table,
        );
    }

    /**
     * A date column in the format of the locale of the backend route (yuf's `DateColumn::useLocale()`).
     */
    protected function createDateColumn(
        string $identifier,
        string $label,
        bool $withTime,
        bool $isSortable = false,
        bool $sortAscendingByDefault = true,
    ): DateColumn {
        $dateColumn = new DateColumn(
            identifier: $identifier,
            label: $label,
            isSortable: $isSortable,
            sortAscendingByDefault: $sortAscendingByDefault,
        );
        $dateColumn->useLocale(
            language: $this->backendContext->route->language,
            timeStyle: $withTime ? DateStyleEnum::MEDIUM : DateStyleEnum::NONE,
        );

        return $dateColumn;
    }

    #[\Override]
    public function render(): string
    {
        $this->fullHtml = DbResultTable::FILTER
            . SmartTable::TOTAL_AMOUNT
            . DbResultTable::PAGINATION
            . '<div class="table-wrap">' . SmartTable::TABLE . '</div>'
            . DbResultTable::PAGINATION;
        return parent::render();
    }
}
