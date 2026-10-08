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
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\form\UserSearchForm;
use actra\backend\view\backend\php\user;
use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\BooleanColumn;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItem;

/**
 * @internal
 */
final class UserTable extends AbstractTable
{
    public function __construct(BackendViewContext $context, UserSearchForm $userSearchForm)
    {
        $dbQuery = DbAuthUserRepository::getDbQuery();
        $dbAuthGroup = $userSearchForm->dbAuthGroup;
        if ($dbAuthGroup !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_user.ID IN (SELECT userID FROM auth_user_group WHERE groupID=?)',
                parameters: [
                    $dbAuthGroup->ID,
                ],
            );
        }
        $searchQuery = $userSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $booleanQuery = SearchQueryBuilder::createBooleanQuery(
                spaceSeparatedFieldNames: 'auth_user.firstName auth_user.lastName auth_user.email',
                queryText: $searchQuery,
            );
            $dbQuery->addWherePart(
                wherePart: $booleanQuery->query,
                parameters: $booleanQuery->params,
            );
        }
        parent::__construct(
            context: $context,
            identifier: 'UserTable',
            dbQuery: $dbQuery,
            itemsPerPage: 100,
        );
        $common = ActraBackend::messages()->common;
        $messages = ActraBackend::messages()->user;
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'fullName',
                label: $messages->nameColumn,
                callbackFunction: static fn(TableItem $tableItem): string => '<a href="' . user::getPath(
                    ID: $tableItem->getRow()->getInt(column: 'ID'),
                ) . '">' . MessageTemplate::fill(
                    template: HtmlEncoder::encode(value: $common->fullName),
                    values: [
                        'firstName' => $tableItem->renderValue(name: 'firstName'),
                        'lastName' => $tableItem->renderValue(name: 'lastName'),
                    ],
                ) . '</a>',
                isSortable: true,
            ),
            isDefaultSortColumn: true,
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'email',
                label: $common->emailLabel,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new BooleanColumn(
                identifier: 'active',
                label: $messages->activeColumn,
                isSortable: true,
                sortAscendingByDefault: false,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'rightGroups',
                label: $messages->rightGroupsColumn,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'ipWhitelist',
                label: $common->ipWhitelistLabel,
                callbackFunction: static fn(TableItem $tableItem): string => str_replace(
                    search: ',',
                    replace: '<br>',
                    subject: $tableItem->renderValue(name: 'ipWhitelist'),
                ),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: $registeredColumn = new DateColumn(
                identifier: 'registered',
                label: $messages->registeredColumn,
                isSortable: true,
            ),
        );
        $registeredColumn->format = $common->dateFormat;
        $this->addColumn(
            abstractTableColumn: $invitedColumn = new DateColumn(
                identifier: 'invited',
                label: $messages->invitedColumn,
                isSortable: true,
            ),
        );
        $invitedColumn->format = $common->dateFormat;
    }
}
