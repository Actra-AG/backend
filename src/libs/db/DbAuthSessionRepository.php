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
        SELECT auth_sessions.id,
               auth_sessions.parent_id,
               auth_users.id AS user_id,
               auth_users.created_at,
               auth_users.invited_at,
               (SELECT MAX(created_at) FROM auth_logins WHERE user_id=auth_users.id) AS last_login,
               auth_users.email,
               auth_users.phone,
               auth_users.is_active,
               auth_users.first_name,
               auth_users.last_name,
               auth_users.language,
               auth_users.password_salt,
               auth_users.password_hash,
               auth_users.wrong_login_attempts,
               (SELECT GROUP_CONCAT(auth_group_right.right_name)
                   FROM auth_group_right
                   WHERE auth_group_right.group_id IN (SELECT group_id
                       FROM auth_group_user
                       WHERE user_id=auth_users.id)) AS access_rights,
               (SELECT GROUP_CONCAT(auth_ip_whitelists.ip_address)
                   FROM auth_ip_whitelists
                   WHERE auth_ip_whitelists.user_id=auth_users.id) AS ip_whitelist
        FROM auth_sessions
            INNER JOIN auth_users ON auth_users.id=auth_sessions.user_id
    ';

    public function insert(
        ?int $parentId,
        int $userId,
        ClientData $clientData,
    ): int {
        $db = $this->db;
        $db->execute(
            sql: '
                INSERT INTO auth_sessions
                SET parent_id=?,
                    user_id=?,
                    php_session_id=?,
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
            sql: DbAuthSessionRepository::SELECT_QUERY . ' WHERE auth_sessions.id=?',
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
                createdAt: $row->getDateTimeImmutable(column: 'created_at'),
                invitedAt: $row->getNullableDateTimeImmutable(column: 'invited_at'),
                lastLogin: $row->getNullableDateTimeImmutable(column: 'last_login'),
                email: $row->getString(column: 'email'),
                phone: $row->getString(column: 'phone'),
                isActive: $row->getBool(column: 'is_active'),
                accessRightCollection: AccessRightCollection::createFromStringArray(
                    input: $row->getStringList(column: 'access_rights'),
                ),
                firstName: $row->getString(column: 'first_name'),
                lastName: $row->getString(column: 'last_name'),
                languageCode: $row->getNullableString(column: 'language'),
                password: $passwordSalt === null ? null : new Password(
                    salt: $passwordSalt,
                    hash: $row->getString(column: 'password_hash'),
                ),
                wrongLoginAttempts: $row->getInt(column: 'wrong_login_attempts'),
                ipWhitelist: $row->getStringList(column: 'ip_whitelist'),
            ),
        );
    }

    public function updateLastAction(int $id, Clock $clock = new SystemClock()): void
    {
        $this->db->execute(
            sql: '
                    UPDATE auth_sessions
                    SET last_action_at=?
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
                    DELETE FROM auth_sessions
                           WHERE id>0
                             AND parent_id IN (SELECT id FROM auth_sessions WHERE user_id=?)
                ',
            parameters: [
                $userId,
            ],
        );
        $db->execute(
            sql: '
                    DELETE FROM auth_sessions
                           WHERE user_id=?
                ',
            parameters: [
                $userId,
            ],
        );
    }

    /**
     * Ends all sessions of a user except one (the current session after a password change), with the impersonation
     * sessions started from them.
     */
    public function deleteOthersByUserId(int $userId, int $keepSessionId): void
    {
        $db = $this->db;
        $db->execute(
            sql: '
                    DELETE FROM auth_sessions
                           WHERE id<>?
                             AND parent_id IN (SELECT id FROM auth_sessions WHERE user_id=? AND id<>?)
                ',
            parameters: [$keepSessionId, $userId, $keepSessionId],
        );
        $db->execute(
            sql: 'DELETE FROM auth_sessions WHERE user_id=? AND id<>?',
            parameters: [$userId, $keepSessionId],
        );
    }
}
