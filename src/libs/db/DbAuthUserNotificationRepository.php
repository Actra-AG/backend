<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbQuery;
use actra\yuf\db\DbRow;

final class DbAuthUserNotificationRepository
{
    public function __construct(private readonly DB $db) {}

    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_user_notification.id,
                       auth_user_notification.auth_group_id,
                       auth_user_notification.sent_by_id,
                       auth_user_notification.sent_date,
                       auth_user_notification.subject,
                       auth_user_notification.message,
                       auth_group.title AS group_name,
                       auth_user.first_name,
                       auth_user.last_name,
                       (SELECT COUNT(id)
                           FROM auth_user_notification_recipient
                           WHERE auth_user_notification_recipient.notification_id=auth_user_notification.id
                       ) AS recipients
                FROM auth_user_notification
                    INNER JOIN auth_group ON auth_user_notification.auth_group_id = auth_group.id
                    INNER JOIN auth_user ON auth_user.id = auth_user_notification.sent_by_id
            ',
        );
    }

    private function createItem(DbRow $row): DbAuthUserNotification
    {
        return new DbAuthUserNotification(
            id: $row->getInt(column: 'id'),
            authGroupId: $row->getInt(column: 'auth_group_id'),
            sentById: $row->getInt(column: 'sent_by_id'),
            sentDate: $row->getDateTimeImmutable(column: 'sent_date'),
            subject: $row->getString(column: 'subject'),
            message: $row->getString(column: 'message'),
            groupName: $row->getString(column: 'group_name'),
            firstName: $row->getString(column: 'first_name'),
            lastName: $row->getString(column: 'last_name'),
            recipients: $row->getInt(column: 'recipients'),
        );
    }

    public function selectById(int $id): ?DbAuthUserNotification
    {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user_notification.id=?',
            parameters: [
                $id,
            ],
        );
        $dbAuthUserNotificationCollection = $this->select(dbQuery: $dbQuery);
        return $dbAuthUserNotificationCollection->isEmpty() ? null : $dbAuthUserNotificationCollection->first();
    }

    public function select(DbQuery $dbQuery): DbAuthUserNotificationCollection
    {
        $dbAuthUserNotificationCollection = new DbAuthUserNotificationCollection();
        foreach (
            $dbQuery->selectRowsFromDb(
                db: $this->db,
                offset: 0,
                rowCount: 1000,
            ) as $row
        ) {
            $dbAuthUserNotificationCollection->add(
                dbAuthUserNotification: $this->createItem(row: $row),
            );
        }

        return $dbAuthUserNotificationCollection;
    }

    public function insert(
        int $authGroupId,
        int $sentByUserId,
        string $subject,
        string $message,
    ): int {
        $db = $this->db;
        $db->execute(
            sql: '
                INSERT INTO auth_user_notification
                SET auth_group_id=?,
                    sent_by_id=?,
                    subject=?,
                    message=?
            ',
            parameters: [
                $authGroupId,
                $sentByUserId,
                $subject,
                $message,
            ],
        );
        return $db->getLastInsertId();
    }
}
