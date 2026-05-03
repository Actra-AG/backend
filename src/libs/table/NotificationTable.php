<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\table;

use actra\backend\libs\db\DB;
use actra\backend\libs\db\DbAuthUserNotificationRepository;
use actra\backend\view\backend\php\notification;
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
        $this->addColumn(
            abstractTableColumn: new DateColumn(
                identifier: 'sentDate',
                label: 'Versanddatum',
                sortAscendingByDefault: false
            ),
            isDefaultSortColumn: true
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'subject',
                label: 'Betreff',
                callbackFunction: function (TableItemModel $tableItemModel) {
                    return '<a href="' . notification::getPath(
                            ID: $tableItemModel->getRawValue(name: 'ID')
                        ) . '">' . $tableItemModel->renderValue(name: 'subject') . '</a>';
                }
            )
        );
        $this->addColumn(
            abstractTableColumn: new CallbackColumn(
                identifier: 'firstName',
                label: 'Sender',
                callbackFunction: function (TableItemModel $tableItemModel) {
                    return $tableItemModel->renderValue(name: 'firstName') . ' ' . $tableItemModel->renderValue(
                            name: 'lastName'
                        );
                }
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'groupName',
                label: 'Benutzergruppe'
            )
        );
        $this->addColumn(
            abstractTableColumn: new DefaultColumn(
                identifier: 'recipients',
                label: 'Empfänger'
            )
        );
    }
}