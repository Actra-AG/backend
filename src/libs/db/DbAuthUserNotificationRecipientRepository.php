<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbQuery;

class DbAuthUserNotificationRecipientRepository
{
    public static function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_user_notification_recipient.ID,
                       auth_user_notification_recipient.sentDate,
                       auth_user_notification_recipient.email,
                       auth_user.firstName,
                       auth_user.lastName
                FROM auth_user_notification_recipient
                    INNER JOIN auth_user ON auth_user.ID = auth_user_notification_recipient.authUserID
            '
        );
    }

    public static function insert(
        int $notificationID,
        int $authUserID,
        string $email
    ): int {
        $db = DB::get();
        $db->execute(
            sql: '
                INSERT INTO auth_user_notification_recipient
                SET notificationID=?,
                    authUserID=?,
                    email=?
            ',
            parameters: [
                $notificationID,
                $authUserID,
                $email,
            ]
        );
        return $db->lastInsertId();
    }
}