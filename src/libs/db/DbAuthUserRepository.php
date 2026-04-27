<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\libs\auth\MyAuthUser;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\db\DbQuery;
use DateTimeImmutable;
use stdClass;

class DbAuthUserRepository
{
    public static function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_user.ID,
                       auth_user.registered,
                       auth_user.invited,
                       (SELECT MAX(registered) FROM auth_login WHERE userID=auth_user.ID) AS lastLogin,
                       auth_user.email,
                       auth_user.active,
                       (SELECT GROUP_CONCAT(auth_group_right.rightName) FROM auth_group_right WHERE auth_group_right.groupID IN (SELECT groupID FROM auth_user_group WHERE userID=auth_user.ID)) AS accessRights,
                       auth_user.firstName,
                       auth_user.lastName,
                       (SELECT GROUP_CONCAT(auth_group.title SEPARATOR \'<br>\') FROM auth_group WHERE auth_group.ID IN (SELECT groupID FROM auth_user_group WHERE userID=auth_user.ID)) AS rightGroups,
                       CONCAT_WS(\' \', auth_user.firstName, auth_user.lastName) AS fullName
                FROM auth_user
            '
        );
    }

    private static function createDbAuthUser(stdClass $data): DbAuthUser
    {
        return new DbAuthUser(
            ID: $data->ID,
            registered: new DateTimeImmutable(datetime: $data->registered),
            invitedDate: is_null(value: $data->invited) ? null : new DateTimeImmutable(datetime: $data->invited),
            lastLogin: is_null(value: $data->lastLogin) ? null : new DateTimeImmutable(datetime: $data->lastLogin),
            email: $data->email,
            isActive: ($data->active === 1),
            accessRightCollection: AccessRightCollection::createFromStringArray(
                input: explode(
                    separator: ',',
                    string: (string)$data->accessRights
                )
            ),
            firstName: $data->firstName,
            lastName: $data->lastName
        );
    }

    public static function select(DbQuery $dbQuery): DbAuthUserCollection
    {
        $dbAuthUserCollection = new DbAuthUserCollection();
        foreach (
            $dbQuery->selectFromDb(
                db: DB::get(),
                offset: 0,
                rowCount: 1000
            ) as $item
        ) {
            $dbAuthUserCollection->add(
                dbAuthUser: DbAuthUserRepository::createDbAuthUser(data: $item)
            );
        }

        return $dbAuthUserCollection;
    }

    public static function selectByID(int $ID): ?DbAuthUser
    {
        $dbQuery = DbAuthUserRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user.ID=?',
            parameters: [
                $ID,
            ]
        );
        $dbAuthUserCollection = DbAuthUserRepository::select(dbQuery: $dbQuery);
        return $dbAuthUserCollection->isEmpty() ? null : $dbAuthUserCollection->first();
    }

    public static function selectByEmail(string $email): ?DbAuthUser
    {
        $dbQuery = DbAuthUserRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user.email=?',
            parameters: [
                $email,
            ]
        );
        $dbAuthUserCollection = DbAuthUserRepository::select(dbQuery: $dbQuery);
        return $dbAuthUserCollection->isEmpty() ? null : $dbAuthUserCollection->first();
    }

    public static function sentInvitation(int $ID): void
    {
        DB::get()->execute(
            sql: '
                    UPDATE auth_user
                    SET invited=NOW()
                    WHERE ID=?
                ',
            parameters: [
                $ID,
            ]
        );
    }

    public static function dbConfirmSuccessfulLogin(int $ID): void
    {
        DB::get()->execute(
            sql: '
                    UPDATE auth_user
                    SET lastSuccessfulLogin=NOW()
                    WHERE ID=?
                ',
            parameters: [
                $ID,
            ]
        );
    }

    public static function delete(int $ID): void
    {
        DB::get()->execute(
            sql: '
                        DELETE FROM auth_user
                               WHERE ID=?
                    ',
            parameters: [
                $ID,
            ]
        );
    }

    public static function insert(
        string $email,
        bool $active,
        string $firstName,
        string $lastName
    ): int {
        $db = DB::get();
        $db->execute(
            sql: '
                            INSERT INTO auth_user
                            SET registeredByID=?,
                                email=?,
                                active=?,
                                firstName=?,
                                lastName=?
                        ',
            parameters: [
                MyAuthUser::get()->ID,
                $email,
                $active ? 1 : 0,
                $firstName,
                $lastName,
            ]
        );

        return $db->lastInsertId();
    }

    public static function update(
        int $ID,
        string $email,
        bool $active,
        string $firstName,
        string $lastName
    ): void {
        $db = DB::get();
        $db->execute(
            sql: '
                            UPDATE auth_user
                            SET email=?,
                                firstName=?,
                                lastName=?,
                                active=?
                            WHERE ID=?
                        ',
            parameters: [
                $email,
                $firstName,
                $lastName,
                $active ? 1 : 0,
                $ID,
            ]
        );
    }
}