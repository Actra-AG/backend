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
                SELECT auth_user_notification.ID,
                       auth_user_notification.authGroupID,
                       auth_user_notification.sentByID,
                       auth_user_notification.sentDate,
                       auth_user_notification.subject,
                       auth_user_notification.message,
                       auth_group.title AS groupName,
                       auth_user.firstName,
                       auth_user.lastName,
                       (SELECT COUNT(ID)
                           FROM auth_user_notification_recipient
                           WHERE auth_user_notification_recipient.notificationID=auth_user_notification.ID
                       ) AS recipients
                FROM auth_user_notification
                    INNER JOIN auth_group ON auth_user_notification.authGroupID = auth_group.ID
                    INNER JOIN auth_user ON auth_user.ID = auth_user_notification.sentByID
            ',
        );
    }

    private function createItem(DbRow $row): DbAuthUserNotification
    {
        return new DbAuthUserNotification(
            ID: $row->getInt(column: 'ID'),
            authGroupID: $row->getInt(column: 'authGroupID'),
            sentByID: $row->getInt(column: 'sentByID'),
            sentDate: $row->getDateTimeImmutable(column: 'sentDate'),
            subject: $row->getString(column: 'subject'),
            message: $row->getString(column: 'message'),
            groupName: $row->getString(column: 'groupName'),
            firstName: $row->getString(column: 'firstName'),
            lastName: $row->getString(column: 'lastName'),
            recipients: $row->getInt(column: 'recipients'),
        );
    }

    public function selectByID(int $ID): ?DbAuthUserNotification
    {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user_notification.ID=?',
            parameters: [
                $ID,
            ],
        );
        $dbAuthUserNotificationCollection = $this->select(dbQuery: $dbQuery);
        return $dbAuthUserNotificationCollection->isEmpty() ? null : $dbAuthUserNotificationCollection->first();
    }

    public function select(DbQuery $dbQuery): DbAuthUserNotificationCollection
    {
        $dbAuthUserNotificationCollection = new DbAuthUserNotificationCollection();
        foreach (
            $this->db->selectRowsFromQuery(
                dbQuery: $dbQuery,
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
        int $authGroupID,
        int $sentByUserID,
        string $subject,
        string $message,
    ): int {
        $db = $this->db;
        $db->execute(
            sql: '
                INSERT INTO auth_user_notification
                SET authGroupID=?,
                    sentByID=?,
                    subject=?,
                    message=?
            ',
            parameters: [
                $authGroupID,
                $sentByUserID,
                $subject,
                $message,
            ],
        );
        return $db->getLastInsertId();
    }
}
