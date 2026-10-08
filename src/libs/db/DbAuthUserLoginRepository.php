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
                SELECT auth_login.registered,
                       auth_user.first_name,
                       auth_user.last_name,
                       auth_login.user_id,
                       auth_login.session_id,
                       auth_login.ip_address,
                       auth_login.email,
                       auth_login.result
                FROM auth_login
                    INNER JOIN auth_user ON auth_user.id=auth_login.user_id
            ',
        );
    }
}
