<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbQuery;

final class DbAuthUserLoginRepository
{
    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_logins.created_at,
                       auth_users.first_name,
                       auth_users.last_name,
                       auth_logins.user_id,
                       auth_logins.php_session_id,
                       auth_logins.ip_address,
                       auth_logins.email,
                       auth_logins.result
                FROM auth_logins
                    INNER JOIN auth_users ON auth_users.id=auth_logins.user_id
            ',
        );
    }
}
