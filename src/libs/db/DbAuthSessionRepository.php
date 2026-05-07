<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\core\HttpRequest;
use DateTimeImmutable;
use stdClass;

class DbAuthSessionRepository
{
    private const string SELECT_QUERY = '
        SELECT auth_session.ID,
               auth_session.parentID,
               auth_user.ID AS userID,
               auth_user.registered,
               auth_user.invited,
               (SELECT MAX(registered) FROM auth_login WHERE userID=auth_user.ID) AS lastLogin,
               auth_user.email,
               auth_user.phone,
               auth_user.active,
               auth_user.firstName,
               auth_user.lastName,
               (SELECT GROUP_CONCAT(auth_group_right.rightName) FROM auth_group_right WHERE auth_group_right.groupID IN (SELECT groupID FROM auth_user_group WHERE userID=auth_user.ID)) AS accessRights,
               (SELECT GROUP_CONCAT(auth_ipWhitelist.ipAddress) FROM auth_ipWhitelist WHERE auth_ipWhitelist.userID=auth_user.ID) AS ipWhitelist
        FROM auth_session
            INNER JOIN auth_user ON auth_user.ID=auth_session.userID
    ';

    public static function insert(
        ?int $parentID,
        int $userID
    ): int {
        $db = DB::get();
        $db->execute(
            sql: '
                INSERT INTO auth_session
                SET parentID=?,
                    userID=?,
                    sessionId=?,
                    ipAddress=?
            ',
            parameters: [
                $parentID,
                $userID,
                session_id(),
                HttpRequest::getRemoteAddress(),
            ]
        );

        return $db->lastInsertId();
    }

    public static function selectByID(int $ID): ?DbAuthSession
    {
        $res = DB::get()->select(
            sql: DbAuthSessionRepository::SELECT_QUERY . ' WHERE auth_session.ID=?',
            parameters: [
                $ID,
            ]
        );

        return $res === [] ? null : DbAuthSessionRepository::createDbAuthSession(data: $res[0]);
    }

    private static function createDbAuthSession(stdClass $data): DbAuthSession
    {
        return new DbAuthSession(
            ID: $data->ID,
            parentID: $data->parentID,
            dbAuthUser: new DbAuthUser(
                ID: $data->userID,
                registered: new DateTimeImmutable(datetime: $data->registered),
                invitedDate: $data->invited === null ? null : new DateTimeImmutable(datetime: $data->invited),
                lastLogin: $data->lastLogin === null ? null : new DateTimeImmutable(datetime: $data->lastLogin),
                email: $data->email,
                phone: $data->phone,
                isActive: ($data->active === 1),
                accessRightCollection: AccessRightCollection::createFromStringArray(
                    input: explode(
                        separator: ',',
                        string: (string)$data->accessRights
                    )
                ),
                firstName: $data->firstName,
                lastName: $data->lastName,
                rawIpWhitelist: (string)$data->ipWhitelist
            )
        );
    }

    public static function updateLastAction(int $ID): void
    {
        DB::get()->execute(
            sql: '
                    UPDATE auth_session
                    SET lastAction=NOW()
                    WHERE ID=?
                ',
            parameters: [$ID]
        );
    }

    public static function deleteByUserID(int $userID): void
    {
        $db = DB::get();
        $db->execute(
            sql: '
                    DELETE FROM auth_session
                           WHERE ID>0
                             AND parentID IN (SELECT ID FROM auth_session WHERE userID=?)
                ',
            parameters: [
                $userID,
            ]
        );
        $db->execute(
            sql: '
                    DELETE FROM auth_session
                           WHERE userID=?
                ',
            parameters: [
                $userID,
            ]
        );
    }
}