<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\BackendViewContext;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\form\UserSearchForm;
use actra\yuf\common\SearchQueryBuilder;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\table\column\BooleanColumn;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItem;

/**
 * @internal
 */
final class UserTable extends AbstractTable
{
    public function __construct(BackendViewContext $context, UserSearchForm $userSearchForm)
    {
        $dbQuery = $context->repositories->users()->getDbQuery();
        $dbAuthGroup = $userSearchForm->dbAuthGroup;
        if ($dbAuthGroup !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_user.id IN (SELECT user_id FROM auth_user_group WHERE group_id=?)',
                parameters: [
                    $dbAuthGroup->id,
                ],
            );
        }
        $searchQuery = $userSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $booleanQuery = SearchQueryBuilder::createBooleanQuery(
                spaceSeparatedFieldNames: 'auth_user.first_name auth_user.last_name auth_user.email',
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
        $common = $context->messages->common;
        $messages = $context->messages->user;
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'full_name',
                label: HtmlText::fromText(text: $messages->nameColumn),
                callbackFunction: static fn(TableItem $tableItem): string => '<a href="' . $context->paths->user(
                    id: $tableItem->getRow()->getInt(column: 'id'),
                ) . '">' . MessageTemplate::fill(
                    template: HtmlEncoder::encode(value: $common->fullName),
                    values: [
                        'firstName' => $tableItem->renderValue(name: 'first_name'),
                        'lastName' => $tableItem->renderValue(name: 'last_name'),
                    ],
                ) . '</a>',
                isSortable: true,
            ),
            isDefaultSortColumn: true,
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'email',
                label: HtmlText::fromText(text: $common->emailLabel),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new BooleanColumn(
                identifier: 'active',
                label: HtmlText::fromText(text: $messages->activeColumn),
                isSortable: true,
                sortAscendingByDefault: false,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'right_groups',
                label: HtmlText::fromText(text: $messages->rightGroupsColumn),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'ip_whitelist',
                label: HtmlText::fromText(text: $common->ipWhitelistLabel),
                callbackFunction: static fn(TableItem $tableItem): string => str_replace(
                    search: ',',
                    replace: '<br>',
                    subject: $tableItem->renderValue(name: 'ip_whitelist'),
                ),
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: $this->createDateColumn(
                identifier: 'registered',
                label: HtmlText::fromText(text: $messages->registeredColumn),
                withTime: false,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: $this->createDateColumn(
                identifier: 'invited',
                label: HtmlText::fromText(text: $messages->invitedColumn),
                withTime: false,
                isSortable: true,
            ),
        );
    }
}
