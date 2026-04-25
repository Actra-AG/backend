<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\libs\db\DB;
use actra\backend\libs\form\VisitSearchForm;
use actra\yuf\auth\AuthResult;
use actra\yuf\db\DbQuery;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItemModel;

class VisitTable extends AbstractTable
{
    public function __construct(
        string $identifier,
        ?int $filterUserID,
        VisitSearchForm $tokenSearchForm
    ) {
        $dbQuery = DbQuery::createFromSqlQuery(
            query: '
				SELECT auth_login.registered,
				       auth_user.firstName,
				       auth_user.lastName,
				       auth_login.userID,
				       auth_login.sessionId,
				       auth_login.ipAddress,
				       auth_login.email,
				       auth_login.result
				FROM auth_login
				    INNER JOIN auth_user ON auth_user.ID=auth_login.userID
				'
        );
        if (!is_null(value: $filterUserID)) {
            $dbQuery->addWherePart(
                wherePart: 'auth_login.userID=?',
                parameters: [
                    $filterUserID,
                ]
            );
        }
        $status = $tokenSearchForm->status;
        if ($status > 0) {
            $dbQuery->addWherePart(
                wherePart: 'auth_login.result=?',
                parameters: [
                    $status,
                ]
            );
        }
        $searchQuery = $tokenSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $dbQuery->addWherePart(
                wherePart: $tokenSearchForm->searchHelper->getBooleanQuery(
                    spaceSeparatedFieldNames: 'auth_user.firstName auth_user.lastName auth_login.sessionId auth_login.ipAddress auth_login.email',
                    query_text: $searchQuery
                ),
                parameters: []
            );
        }
        parent::__construct(
            identifier: $identifier,
            db: DB::get(),
            dbQuery: $dbQuery,
            itemsPerPage: 100
        );
        $this->addColumn(
            abstractTableColumn: new DateColumn(
                identifier: 'registered',
                label: 'Datum',
                isSortable: true,
                sortAscendingByDefault: false
            ),
            isDefaultSortColumn: true
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'firstName',
                label: 'Vorname',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'lastName',
                label: 'Nachname',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'sessionId',
                label: 'SessionID',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'ipAddress',
                label: 'IP-Adresse',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'email',
                label: 'E-Mail-Adresse',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'result',
                label: 'Status',
                callbackFunction: function (TableItemModel $tableItemModel) {
                    return AuthResult::from(value: $tableItemModel->getRawValue(name: 'result'))->render();
                }
            )
        );
    }
}