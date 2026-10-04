<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DB;
use actra\backend\libs\db\DbAuthUserNotificationRepository;
use actra\backend\view\backend\php\notification;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\column\DateColumn;
use actra\yuf\table\column\DefaultColumn;
use actra\yuf\table\TableItemModel;

class NotificationTable extends AbstractTable
{
    public function __construct()
    {
        $dbQuery = DbAuthUserNotificationRepository::getDbQuery();
        parent::__construct(
            identifier: 'NotificationTable',
            db: DB::get(),
            dbQuery: $dbQuery,
            itemsPerPage: 100
        );
        $messages = ActraBackend::messages();
        $sentDateColumn = new DateColumn(
            identifier: 'sentDate',
            label: $messages->notification->sentDateLabel,
            sortAscendingByDefault: false
        );
        $sentDateColumn->format = $messages->common->dateTimeFormat;
        $this->addColumn(
            abstractTableColumn: $sentDateColumn,
            isDefaultSortColumn: true
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'subject',
                label: $messages->common->subjectLabel,
                callbackFunction: static function (TableItemModel $tableItemModel): string {
                    $notificationID = $tableItemModel->getRawValue(name: 'ID');

                    return '<a href="' . HtmlEncoder::encode(
                            value: notification::getPath(
                                ID: is_numeric(value: $notificationID) ? (int)$notificationID : 0
                            )
                        ) . '">' . $tableItemModel->renderValue(name: 'subject') . '</a>';
                }
            )
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'firstName',
                label: $messages->notification->senderLabel,
                callbackFunction: static function (TableItemModel $tableItemModel): string {
                    return $tableItemModel->renderValue(name: 'firstName') . ' ' . $tableItemModel->renderValue(
                            name: 'lastName'
                        );
                }
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'groupName',
                label: $messages->common->userGroupLabel
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'recipients',
                label: $messages->notification->recipientsLabel
            )
        );
    }
}