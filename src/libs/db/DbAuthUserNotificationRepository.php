<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\libs\auth\MyAuthUser;
use actra\yuf\db\DbQuery;
use DateTimeImmutable;
use stdClass;

class DbAuthUserNotificationRepository
{
    public static function getDbQuery(): DbQuery
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
                       (SELECT COUNT(ID) FROM auth_user_notification_recipient WHERE auth_user_notification_recipient.notificationID=auth_user_notification.ID) AS recipients
                FROM auth_user_notification
                    INNER JOIN auth_group ON auth_user_notification.authGroupID = auth_group.ID
                    INNER JOIN auth_user ON auth_user.ID = auth_user_notification.sentByID
            '
        );
    }

    private static function createItem(stdClass $data): DbAuthUserNotification
    {
        return new DbAuthUserNotification(
            ID: $data->ID,
            authGroupID: $data->authGroupID,
            sentByID: $data->sentByID,
            sentDate: new DateTimeImmutable(datetime: $data->sentDate),
            subject: $data->subject,
            message: $data->message,
            groupName: $data->groupName,
            firstName: $data->firstName,
            lastName: $data->lastName,
            recipients: $data->recipients
        );
    }

    public static function selectByID(int $ID): ?DbAuthUserNotification
    {
        $dbQuery = DbAuthUserNotificationRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user_notification.ID=?',
            parameters: [
                $ID,
            ]
        );
        $dbAuthUserNotificationCollection = DbAuthUserNotificationRepository::select(dbQuery: $dbQuery);
        return $dbAuthUserNotificationCollection->isEmpty() ? null : $dbAuthUserNotificationCollection->first();
    }

    public static function select(DbQuery $dbQuery): DbAuthUserNotificationCollection
    {
        $dbAuthUserNotificationCollection = new DbAuthUserNotificationCollection();
        foreach (
            $dbQuery->selectFromDb(
                db: DB::get(),
                offset: 0,
                rowCount: 1000
            ) as $item
        ) {
            $dbAuthUserNotificationCollection->add(
                dbAuthUserNotification: DbAuthUserNotificationRepository::createItem(data: $item)
            );
        }

        return $dbAuthUserNotificationCollection;
    }

    public static function insert(
        int $authGroupID,
        string $subject,
        string $message
    ): int {
        $db = DB::get();
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
                MyAuthUser::get()->ID,
                $subject,
                $message,
            ]
        );
        return $db->lastInsertId();
    }
}