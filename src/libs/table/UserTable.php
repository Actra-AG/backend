<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\libs\db\DB;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\UserSearchForm;
use actra\backend\view\backend\php\user;
use actra\yuf\table\column\BooleanColumn;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItemModel;

class UserTable extends AbstractTable
{
    public function __construct(UserSearchForm $userSearchForm)
    {
        $dbQuery = DbAuthUserRepository::getDbQuery();
        $dbAuthGroup = $userSearchForm->dbAuthGroup;
        if ($dbAuthGroup !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_user.ID IN (SELECT userID FROM auth_user_group WHERE groupID=?)',
                parameters: [
                    $dbAuthGroup->ID,
                ]
            );
        }
        $searchQuery = $userSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $dbQuery->addWherePart(
                wherePart: $userSearchForm->searchHelper->getBooleanQuery(
                    spaceSeparatedFieldNames: 'auth_user.firstName auth_user.lastName auth_user.email',
                    query_text: $searchQuery
                ),
                parameters: []
            );
        }
        parent::__construct(
            identifier: 'UserTable',
            db: DB::get(),
            dbQuery: $dbQuery,
            itemsPerPage: 100
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'fullName',
                label: 'Name',
                callbackFunction: function (TableItemModel $tableItemModel) {
                    return '<a href="' . user::getPath(
                            ID: $tableItemModel->getRawValue(name: 'ID')
                        ) . '">' . $tableItemModel->renderValue(name: 'fullName') . '</a>';
                },
                isSortable: true
            ),
            isDefaultSortColumn: true
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'email',
                label: 'E-Mail',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: new BooleanColumn(
                identifier: 'active',
                label: 'Aktiv',
                isSortable: true,
                sortAscendingByDefault: false
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'rightGroups',
                label: 'Rechtegruppe(n)',
                isSortable: true
            )
        );
        $this->addColumn(
            abstractTableColumn: $registeredColumn = new DateColumn(
                identifier: 'registered',
                label: 'erfasst',
                isSortable: true
            )
        );
        $registeredColumn->format = 'd.m.Y';
        $this->addColumn(
            abstractTableColumn: $invitedColumn = new DateColumn(
                identifier: 'invited',
                label: 'eingeladen',
                isSortable: true
            )
        );
        $invitedColumn->format = 'd.m.Y';
    }
}