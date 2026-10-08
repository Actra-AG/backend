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
use actra\backend\libs\db\DbAuthUserNotificationRepository;
use actra\backend\view\backend\php\notification;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItem;

/**
 * @internal
 */
final class NotificationTable extends AbstractTable
{
    public function __construct(BackendViewContext $context)
    {
        $dbQuery = DbAuthUserNotificationRepository::getDbQuery();
        parent::__construct(
            context: $context,
            identifier: 'NotificationTable',
            dbQuery: $dbQuery,
            itemsPerPage: 100,
        );
        $messages = ActraBackend::messages();
        $sentDateColumn = new DateColumn(
            identifier: 'sentDate',
            label: $messages->notification->sentDateLabel,
            sortAscendingByDefault: false,
        );
        $sentDateColumn->format = $messages->common->dateTimeFormat;
        $this->addColumn(
            abstractTableColumn: $sentDateColumn,
            isDefaultSortColumn: true,
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'subject',
                label: $messages->common->subjectLabel,
                callbackFunction: static fn(TableItem $tableItem): string => '<a href="' . HtmlEncoder::encode(
                    value: notification::getPath(ID: $tableItem->getRow()->getInt(column: 'ID')),
                ) . '">' . $tableItem->renderValue(name: 'subject') . '</a>',
            ),
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'firstName',
                label: $messages->notification->senderLabel,
                callbackFunction: static fn(TableItem $tableItem): string => MessageTemplate::fill(
                    template: HtmlEncoder::encode(value: $messages->common->fullName),
                    values: [
                        'firstName' => $tableItem->renderValue(name: 'firstName'),
                        'lastName' => $tableItem->renderValue(name: 'lastName'),
                    ],
                ),
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'groupName',
                label: $messages->common->userGroupLabel,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'recipients',
                label: $messages->notification->recipientsLabel,
            ),
        );
    }
}
