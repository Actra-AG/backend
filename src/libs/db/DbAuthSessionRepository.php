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
use actra\yuf\db\DbRow;

final class DbAuthSessionRepository
{
    public function __construct(private readonly DB $db) {}

    private const string SELECT_QUERY = '
        SELECT auth_session.id,
               auth_session.parent_id,
               auth_user.id AS user_id,
               auth_user.registered,
               auth_user.invited,
               (SELECT MAX(registered) FROM auth_login WHERE user_id=auth_user.id) AS last_login,
               auth_user.email,
               auth_user.phone,
               auth_user.active,
               auth_user.first_name,
               auth_user.last_name,
               auth_user.language,
               auth_user.password_salt,
               auth_user.password_hash,
               auth_user.wrong_login_attempts,
               (SELECT GROUP_CONCAT(auth_group_right.right_name)
                   FROM auth_group_right
                   WHERE auth_group_right.group_id IN (SELECT group_id
                       FROM auth_user_group
                       WHERE user_id=auth_user.id)) AS access_rights,
               (SELECT GROUP_CONCAT(auth_ip_whitelist.ip_address)
                   FROM auth_ip_whitelist
                   WHERE auth_ip_whitelist.user_id=auth_user.id) AS ip_whitelist
        FROM auth_session
            INNER JOIN auth_user ON auth_user.id=auth_session.user_id
    ';

    public function insert(
        ?int $parentId,
        int $userId,
        ClientData $clientData,
    ): int {
        $db = $this->db;
        $db->execute(
            sql: '
                INSERT INTO auth_session
                SET parent_id=?,
                    user_id=?,
                    session_id=?,
                    ip_address=?
            ',
            parameters: [
                $parentId,
                $userId,
                $clientData->sessionId,
                $clientData->ipAddress,
            ],
        );

        return $db->getLastInsertId();
    }

    public function selectById(int $id): ?DbAuthSession
    {
        $row = $this->db->selectRow(
            sql: DbAuthSessionRepository::SELECT_QUERY . ' WHERE auth_session.id=?',
            parameters: [
                $id,
            ],
        );

        return $row === null ? null : $this->createDbAuthSession(row: $row);
    }

    private function createDbAuthSession(DbRow $row): DbAuthSession
    {
        $passwordSalt = $row->getNullableString(column: 'password_salt');

        return new DbAuthSession(
            id: $row->getInt(column: 'id'),
            parentId: $row->getNullableInt(column: 'parent_id'),
            dbAuthUser: new DbAuthUser(
                id: $row->getInt(column: 'user_id'),
                registered: $row->getDateTimeImmutable(column: 'registered'),
                invitedDate: $row->getNullableDateTimeImmutable(column: 'invited'),
                lastLogin: $row->getNullableDateTimeImmutable(column: 'last_login'),
                email: $row->getString(column: 'email'),
                phone: $row->getString(column: 'phone'),
                isActive: $row->getBool(column: 'active'),
                accessRightCollection: AccessRightCollection::createFromStringArray(
                    input: explode(
                        separator: ',',
                        string: $row->getNullableString(column: 'access_rights') ?? '',
                    ),
                ),
                firstName: $row->getString(column: 'first_name'),
                lastName: $row->getString(column: 'last_name'),
                languageCode: $row->getNullableString(column: 'language'),
                password: $passwordSalt === null ? null : new Password(
                    salt: $passwordSalt,
                    hash: $row->getString(column: 'password_hash'),
                ),
                wrongLoginAttempts: $row->getInt(column: 'wrong_login_attempts'),
                rawIpWhitelist: $row->getNullableString(column: 'ip_whitelist') ?? '',
            ),
        );
    }

    public function updateLastAction(int $id, Clock $clock = new SystemClock()): void
    {
        $this->db->execute(
            sql: '
                    UPDATE auth_session
                    SET last_action=?
                    WHERE id=?
                ',
            parameters: [$clock->now()->format(format: 'Y-m-d H:i:s'), $id],
        );
    }

    public function deleteByUserId(int $userId): void
    {
        $db = $this->db;
        $db->execute(
            sql: '
                    DELETE FROM auth_session
                           WHERE id>0
                             AND parent_id IN (SELECT id FROM auth_session WHERE user_id=?)
                ',
            parameters: [
                $userId,
            ],
        );
        $db->execute(
            sql: '
                    DELETE FROM auth_session
                           WHERE user_id=?
                ',
            parameters: [
                $userId,
            ],
        );
    }
}
