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
use actra\yuf\table\column\AbstractTableColumn;
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
        $context->userScopeGuard->restrictUserQuery(dbQuery: $dbQuery, userIdColumn: 'auth_users.id');
        $dbAuthGroup = $userSearchForm->dbAuthGroup;
        if ($dbAuthGroup !== null) {
            $dbQuery->addWherePart(
                wherePart: 'auth_users.id IN (SELECT user_id FROM auth_group_user WHERE group_id=?)',
                parameters: [
                    $dbAuthGroup->id,
                ],
            );
        }
        $searchQuery = $userSearchForm->searchQuery;
        if ($searchQuery !== '') {
            $booleanQuery = SearchQueryBuilder::createBooleanQuery(
                spaceSeparatedFieldNames: 'auth_users.first_name auth_users.last_name auth_users.email',
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
        $this->addSortTieBreaker(column: 'auth_users.id');
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
                identifier: 'is_active',
                label: HtmlText::fromText(text: $messages->activeColumn),
                isSortable: true,
                sortAscendingByDefault: false,
            ),
        );
        $this->addColumn(abstractTableColumn: $this->createGroupsColumn(context: $context));
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
                identifier: 'created_at',
                label: HtmlText::fromText(text: $messages->registeredColumn),
                withTime: false,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: $this->createDateColumn(
                identifier: 'invited_at',
                label: HtmlText::fromText(text: $messages->invitedColumn),
                withTime: false,
                isSortable: true,
            ),
        );
    }

    /**
     * The groups of the user; for a user restricted by a `UserScope` only the groups it may see, not sortable (the
     * order would depend on the hidden groups).
     */
    private function createGroupsColumn(BackendViewContext $context): AbstractTableColumn
    {
        $label = HtmlText::fromText(text: $context->messages->user->rightGroupsColumn);
        $userScopeGuard = $context->userScopeGuard;
        if (!$userScopeGuard->isRestricted()) {
            return new DefaultColumn(identifier: 'right_groups', label: $label, isSortable: true);
        }
        $visibleGroups = $userScopeGuard->filterGroups(groups: $context->repositories->groups()->listAll());

        return new CallbackColumn(
            identifier: 'right_groups',
            label: $label,
            callbackFunction: static function (TableItem $tableItem) use ($visibleGroups): string {
                $titles = [];
                foreach ($tableItem->getRow()->getStringList(column: 'right_group_ids') as $groupId) {
                    if (array_key_exists(key: (int) $groupId, array: $visibleGroups->items)) {
                        $titles[] = $visibleGroups->items[(int) $groupId]->title;
                    }
                }

                return nl2br(string: HtmlEncoder::encode(value: implode(separator: PHP_EOL, array: $titles)));
            },
        );
    }
}
