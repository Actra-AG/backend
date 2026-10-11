<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbQuery;

final class DbAuthUserNotificationRecipientRepository
{
    public function __construct(private readonly DB $db) {}

    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_user_notification_recipients.id,
                       auth_user_notification_recipients.sent_at,
                       auth_user_notification_recipients.email,
                       auth_users.first_name,
                       auth_users.last_name
                FROM auth_user_notification_recipients
                    INNER JOIN auth_users ON auth_users.id = auth_user_notification_recipients.user_id
            ',
        );
    }

    public function insert(
        int $notificationId,
        int $authUserId,
        string $email,
    ): int {
        $db = $this->db;
        $db->execute(
            sql: '
                INSERT INTO auth_user_notification_recipients
                SET user_notification_id=?,
                    user_id=?,
                    email=?
            ',
            parameters: [
                $notificationId,
                $authUserId,
                $email,
            ],
        );
        return $db->getLastInsertId();
    }
}
