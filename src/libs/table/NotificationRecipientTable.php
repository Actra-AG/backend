<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\BackendViewContext;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;

/**
 * @internal
 */
final class NotificationRecipientTable extends AbstractTable
{
    public function __construct(BackendViewContext $context, int $notificationId)
    {
        $dbQuery = $context->repositories->notificationRecipients()->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user_notification_recipient.notification_id=?',
            parameters: [$notificationId],
        );
        parent::__construct(
            context: $context,
            identifier: 'NotificationRecipientTable-' . $notificationId,
            dbQuery: $dbQuery,
            itemsPerPage: 100,
        );
        $messages = $context->messages;
        $sentDateColumn = new DateColumn(
            identifier: 'sent_date',
            label: $messages->notification->dateLabel,
            isSortable: true,
            sortAscendingByDefault: false,
        );
        $sentDateColumn->format = $messages->common->dateTimeFormat;
        $this->addColumn(
            abstractTableColumn: $sentDateColumn,
            isDefaultSortColumn: true,
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'email',
                label: $messages->common->emailLabel,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'first_name',
                label: $messages->common->firstNameLabel,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'last_name',
                label: $messages->common->lastNameLabel,
                isSortable: true,
            ),
        );
    }
}
