<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\BackendViewContext;
use actra\backend\libs\form\VisitSearchForm;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\html\HtmlText;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItem;

/**
 * @internal
 */
final class VisitTable extends AbstractTable
{
    public function __construct(
        BackendViewContext $context,
        string $identifier,
        ?int $filterUserId,
        VisitSearchForm $tokenSearchForm,
    ) {
        $dbQuery = $context->repositories->userLogins()->getDbQuery();
        if ($filterUserId !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_login.user_id=?',
                parameters: [
                    $filterUserId,
                ],
            );
        }
        $status = $tokenSearchForm->status;
        if ($status !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_login.result=?',
                parameters: [
                    $status->value,
                ],
            );
        }
        $searchQuery = $tokenSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $booleanQuery = SearchQueryBuilder::createBooleanQuery(
                spaceSeparatedFieldNames: 'auth_user.first_name auth_user.last_name auth_login.session_id '
                    . 'auth_login.ip_address auth_login.email',
                queryText: $searchQuery,
            );
            $dbQuery->addWherePart(
                wherePart: $booleanQuery->query,
                parameters: $booleanQuery->params,
            );
        }
        parent::__construct(
            context: $context,
            identifier: $identifier,
            dbQuery: $dbQuery,
            itemsPerPage: 100,
        );
        $messages = $context->messages;
        $dateColumn = $this->createDateColumn(
            identifier: 'registered',
            label: HtmlText::fromText(text: $messages->log->visitDateColumn),
            withTime: true,
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $this->addColumn(abstractTableColumn: $dateColumn, isDefaultSortColumn: true);
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'first_name',
                label: HtmlText::fromText(text: $messages->common->firstNameLabel),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'last_name',
                label: HtmlText::fromText(text: $messages->common->lastNameLabel),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'session_id',
                label: HtmlText::fromText(text: $messages->log->visitSessionIdColumn),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'ip_address',
                label: HtmlText::fromText(text: $messages->log->visitIpAddressColumn),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'email',
                label: HtmlText::fromText(text: $messages->log->visitEmailColumn),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'result',
                label: HtmlText::fromText(text: $messages->log->statusLabel),
                callbackFunction: static fn(TableItem $tableItem): string => $tableItem->getRow()->getEnum(
                    column: 'result',
                    enumClass: AuthResultEnum::class,
                )->label(messages: $messages->authResult)->render(),
            ),
        );
    }
}
