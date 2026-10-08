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
                SELECT auth_user_notification_recipient.id,
                       auth_user_notification_recipient.sent_date,
                       auth_user_notification_recipient.email,
                       auth_user.first_name,
                       auth_user.last_name
                FROM auth_user_notification_recipient
                    INNER JOIN auth_user ON auth_user.id = auth_user_notification_recipient.auth_user_id
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
                INSERT INTO auth_user_notification_recipient
                SET notification_id=?,
                    auth_user_id=?,
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
