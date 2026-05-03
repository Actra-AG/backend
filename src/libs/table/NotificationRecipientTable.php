<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\libs\db\DB;
use actra\backend\libs\db\DbAuthUserNotificationRecipientRepository;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;

class NotificationRecipientTable extends AbstractTable
{
    public function __construct(int $notificationID)
    {
        $dbQuery = DbAuthUserNotificationRecipientRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user_notification_recipient.notificationID=?',
            parameters: [$notificationID]
        );
        parent::__construct(
            identifier: 'NotificationRecipientTable-' . $notificationID,
            db: DB::get(),
            dbQuery: $dbQuery,
            itemsPerPage: 100
        );
        $this->addColumn(
            abstractTableColumn: new DateColumn(
                identifier: 'sentDate',
                label: 'Datum',
                isSortable: true,
                sortAscendingByDefault: false
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
    }
}