<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbQuery;

class DbAuthUserLoginRepository
{
    public static function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_login.registered,
                       auth_user.firstName,
                       auth_user.lastName,
                       auth_login.userID,
                       auth_login.sessionId,
                       auth_login.ipAddress,
                       auth_login.email,
                       auth_login.result
                FROM auth_login
                    INNER JOIN auth_user ON auth_user.ID=auth_login.userID
            '
        );
    }
}