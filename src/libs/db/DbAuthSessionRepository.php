<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\Password;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpRequest;
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
               auth_user.language,
               auth_user.passwordSalt,
               auth_user.passwordHash,
               auth_user.wrongLoginAttempts,
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
        $row = new DbRowReader(row: $data);
        $passwordSalt = $row->getNullableString(column: 'passwordSalt');

        return new DbAuthSession(
            ID: $row->getInt(column: 'ID'),
            parentID: $row->getNullableInt(column: 'parentID'),
            dbAuthUser: new DbAuthUser(
                ID: $row->getInt(column: 'userID'),
                registered: $row->getDateTime(column: 'registered'),
                invitedDate: $row->getNullableDateTime(column: 'invited'),
                lastLogin: $row->getNullableDateTime(column: 'lastLogin'),
                email: $row->getString(column: 'email'),
                phone: $row->getString(column: 'phone'),
                isActive: $row->getBool(column: 'active'),
                accessRightCollection: AccessRightCollection::createFromStringArray(
                    input: explode(
                        separator: ',',
                        string: $row->getStringOrEmpty(column: 'accessRights')
                    )
                ),
                firstName: $row->getString(column: 'firstName'),
                lastName: $row->getString(column: 'lastName'),
                languageCode: $row->getNullableString(column: 'language'),
                password: $passwordSalt === null ? null : new Password(
                    salt: $passwordSalt, hash: $row->getString(column: 'passwordHash')
                ),
                wrongLoginAttempts: $row->getInt(column: 'wrongLoginAttempts'),
                rawIpWhitelist: $row->getStringOrEmpty(column: 'ipWhitelist')
            )
        );
    }

    public static function updateLastAction(int $ID, Clock $clock = new SystemClock()): void
    {
        DB::get()->execute(
            sql: '
                    UPDATE auth_session
                    SET lastAction=?
                    WHERE ID=?
                ',
            parameters: [$clock->now()->format(format: 'Y-m-d H:i:s'), $ID]
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