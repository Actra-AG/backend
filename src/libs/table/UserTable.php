<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\libs\db\DB;
use actra\backend\libs\form\UserSearchForm;
use actra\backend\view\backend\php\user;
use actra\yuf\db\DbQuery;
use actra\yuf\table\column\ActionsColumn;
use actra\yuf\table\column\BooleanColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;

class UserTable extends AbstractTable
{
    public function __construct(UserSearchForm $userSearchForm)
    {
        $dbQuery = DbQuery::createFromSqlQuery(
            query: '
					SELECT auth_user.ID,
					       auth_user.firstName,
					       auth_user.lastName,
					       auth_user.email,
					       auth_user.active,
					       (SELECT GROUP_CONCAT(auth_group.title SEPARATOR \'<br>\') FROM auth_group WHERE auth_group.ID IN (SELECT groupID FROM auth_user_group WHERE userID=auth_user.ID)) AS rightGroups,
					       auth_user.registered,
					       auth_user.invited
					FROM auth_user
				'
        );
        $dbAuthGroupItem = $userSearchForm->dbAuthGroup;
        if (!is_null(value: $dbAuthGroupItem)) {
            $dbQuery->addWherePart(
                wherePart: 'auth_user.ID IN (SELECT userID FROM auth_user_group WHERE groupID=?)',
                parameters: [
                    $dbAuthGroupItem->ID,
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
            abstractTableColumn: new DefaultColumn(
                identifier: 'ID',
                label: 'ID',
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
        $this->addColumn(abstractTableColumn: $detailsColumn = new ActionsColumn(label: 'Details'));
        $detailsColumn->addCellCssClass(className: 'show');
        $detailsColumn->addIndividualActionLink(
            identifier: 'details',
            linkHTML: '<a class="details" href="' . user::getPath(ID: '[ID]') . '">anzeigen</a>'
        );
    }
}