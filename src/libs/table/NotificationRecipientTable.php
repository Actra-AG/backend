<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\ActraBackend;
use actra\backend\BackendViewContext;
use actra\backend\libs\db\DbAuthUserNotificationRecipientRepository;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;

/**
 * @internal
 */
final class NotificationRecipientTable extends AbstractTable
{
    public function __construct(BackendViewContext $context, int $notificationID)
    {
        $dbQuery = DbAuthUserNotificationRecipientRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user_notification_recipient.notificationID=?',
            parameters: [$notificationID],
        );
        parent::__construct(
            context: $context,
            identifier: 'NotificationRecipientTable-' . $notificationID,
            dbQuery: $dbQuery,
            itemsPerPage: 100,
        );
        $messages = ActraBackend::messages();
        $sentDateColumn = new DateColumn(
            identifier: 'sentDate',
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
                identifier: 'firstName',
                label: $messages->common->firstNameLabel,
                isSortable: true,
            ),
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'lastName',
                label: $messages->common->lastNameLabel,
                isSortable: true,
            ),
        );
    }
}
