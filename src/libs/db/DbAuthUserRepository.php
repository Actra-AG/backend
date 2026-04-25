<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\libs\auth\MyAuthUser;
use actra\yuf\auth\AccessRightCollection;
use DateTimeImmutable;
use stdClass;

class DbAuthUserRepository
{
    public const string SELECT_QUERY = '
		SELECT auth_user.ID,
		       auth_user.registered,
 		       auth_user.invited,
		       (SELECT MAX(registered) FROM auth_login WHERE userID=auth_user.ID) AS lastLogin,
		       auth_user.email,
		       auth_user.active,
		       auth_user.firstName,
		       auth_user.lastName,
		       (SELECT GROUP_CONCAT(auth_group_right.rightName) FROM auth_group_right WHERE auth_group_right.groupID IN (SELECT groupID FROM auth_user_group WHERE userID=auth_user.ID)) AS accessRights
		FROM auth_user
	';

    public static function listByCond(string $whereCond, array $parameters): DbAuthUserCollection
    {
        $dbAuthUserCollection = new DbAuthUserCollection();
        foreach (
            DB::get()->select(
                sql: DbAuthUserRepository::SELECT_QUERY . $whereCond . ' ORDER BY auth_user.firstName, auth_user.lastName',
                parameters: $parameters
            ) as $item
        ) {
            $dbAuthUserCollection->add(dbAuthUser: DbAuthUserRepository::createDbAuthUser(data: $item));
        }

        return $dbAuthUserCollection;
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

    public static function selectByID(int $ID): ?DbAuthUser
    {
        return DbAuthUserRepository::selectByCond(
            whereCond: 'WHERE auth_user.ID=?',
            parameters: [
                $ID,
            ]
        );
    }

    private static function selectByCond(string $whereCond, array $parameters): ?DbAuthUser
    {
        $res = DB::get()->select(sql: DbAuthUserRepository::SELECT_QUERY . $whereCond, parameters: $parameters);

        return (count(value: $res) === 1) ? DbAuthUserRepository::createDbAuthUser(data: $res[0]) : null;
    }

    public static function selectByEmail(string $email): ?DbAuthUser
    {
        return DbAuthUserRepository::selectByCond(
            whereCond: 'WHERE auth_user.email=?',
            parameters: [
                $email,
            ]
        );
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