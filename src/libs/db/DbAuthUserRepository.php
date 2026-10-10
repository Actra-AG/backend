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
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbRow;

final class DbAuthUserRepository
{
    public function __construct(private readonly DB $db) {}

    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_user.id,
                       auth_user.registered,
                       auth_user.invited,
                       (SELECT MAX(registered) FROM auth_login WHERE user_id=auth_user.id) AS last_login,
                       auth_user.email,
                       auth_user.phone,
                       auth_user.active,
                       (SELECT GROUP_CONCAT(auth_group_right.right_name)
                           FROM auth_group_right
                           WHERE auth_group_right.group_id IN (SELECT group_id
                               FROM auth_user_group
                               WHERE user_id=auth_user.id)) AS access_rights,
                       auth_user.first_name,
                       auth_user.last_name,
                       auth_user.language,
                       auth_user.password_salt,
                       auth_user.password_hash,
                       auth_user.wrong_login_attempts,
                       (SELECT GROUP_CONCAT(auth_group.title SEPARATOR \'<br>\')
                           FROM auth_group
                           WHERE auth_group.id IN (SELECT group_id
                               FROM auth_user_group
                               WHERE user_id=auth_user.id)) AS right_groups,
                       CONCAT_WS(\' \', auth_user.first_name, auth_user.last_name) AS full_name,
                       (SELECT GROUP_CONCAT(auth_ip_whitelist.ip_address)
                           FROM auth_ip_whitelist
                           WHERE auth_ip_whitelist.user_id=auth_user.id) AS ip_whitelist
                FROM auth_user
            ',
        );
    }

    private function createItem(DbRow $row): DbAuthUser
    {
        $passwordSalt = $row->getNullableString(column: 'password_salt');

        return new DbAuthUser(
            id: $row->getInt(column: 'id'),
            registered: $row->getDateTimeImmutable(column: 'registered'),
            invitedDate: $row->getNullableDateTimeImmutable(column: 'invited'),
            lastLogin: $row->getNullableDateTimeImmutable(column: 'last_login'),
            email: $row->getString(column: 'email'),
            phone: $row->getString(column: 'phone'),
            isActive: $row->getBool(column: 'active'),
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
        );
    }

    public function select(DbQuery $dbQuery): DbAuthUserCollection
    {
        $dbAuthUserCollection = new DbAuthUserCollection();
        foreach (
            $dbQuery->selectRowsFromDb(
                db: $this->db,
                offset: 0,
                rowCount: 1000,
            ) as $row
        ) {
            $dbAuthUserCollection->add(
                dbAuthUser: $this->createItem(row: $row),
            );
        }

        return $dbAuthUserCollection;
    }

    public function selectById(int $id): ?DbAuthUser
    {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user.id=?',
            parameters: [
                $id,
            ],
        );
        $dbAuthUserCollection = $this->select(dbQuery: $dbQuery);
        return $dbAuthUserCollection->isEmpty() ? null : $dbAuthUserCollection->first();
    }

    public function selectByEmail(string $email): ?DbAuthUser
    {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user.email=?',
            parameters: [
                $email,
            ],
        );
        $dbAuthUserCollection = $this->select(dbQuery: $dbQuery);
        return $dbAuthUserCollection->isEmpty() ? null : $dbAuthUserCollection->first();
    }

    public function selectByUserGroup(
        int $groupId,
        bool $mustBeActive = true,
    ): DbAuthUserCollection {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_user.id IN (SELECT user_id FROM auth_user_group WHERE group_id=?)',
            parameters: [
                $groupId,
            ],
        );
        if ($mustBeActive) {
            $dbQuery->addWherePart(
                wherePart: 'auth_user.active=1',
                parameters: [],
            );
        }
        return $this->select(dbQuery: $dbQuery);
    }

    /**
     * The active users with a right, without one user (e.g. to keep at least one user who manages users).
     */
    public function countActiveWithRight(string $accessRight, int $exceptUserId): int
    {
        return $this->db->selectRow(
            sql: '
                SELECT COUNT(DISTINCT auth_user.id) AS amount
                FROM auth_user
                    INNER JOIN auth_user_group ON auth_user_group.user_id=auth_user.id
                    INNER JOIN auth_group_right ON auth_group_right.group_id=auth_user_group.group_id
                WHERE auth_user.active=1
                  AND auth_group_right.right_name=?
                  AND auth_user.id<>?
            ',
            parameters: [$accessRight, $exceptUserId],
        )?->getInt(column: 'amount') ?? 0;
    }

    public function sentInvitation(int $id, Clock $clock = new SystemClock()): void
    {
        $this->db->execute(
            sql: '
                    UPDATE auth_user
                    SET invited=?
                    WHERE id=?
                ',
            parameters: [
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $id,
            ],
        );
    }

    public function dbConfirmSuccessfulLogin(int $id, Clock $clock = new SystemClock()): void
    {
        $this->db->execute(
            sql: '
                    UPDATE auth_user
                    SET last_successful_login=?
                    WHERE id=?
                ',
            parameters: [
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $id,
            ],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute(
            sql: '
                        DELETE FROM auth_user
                               WHERE id=?
                    ',
            parameters: [
                $id,
            ],
        );
    }

    public function insert(
        ?int $registeredById,
        string $email,
        string $phone,
        bool $active,
        string $firstName,
        string $lastName,
        ?string $languageCode,
    ): int {
        $db = $this->db;
        $db->execute(
            sql: '
            INSERT INTO auth_user
            SET registered_by_id=?,
                email=?,
                phone=?,
                active=?,
                first_name=?,
                last_name=?,
                language=?
        ',
            parameters: [
                $registeredById,
                $email,
                $phone,
                $active ? 1 : 0,
                $firstName,
                $lastName,
                $languageCode,
            ],
        );

        return $db->getLastInsertId();
    }

    public function update(
        int $id,
        string $email,
        string $phone,
        bool $active,
        string $firstName,
        string $lastName,
        ?string $languageCode,
    ): void {
        $db = $this->db;
        $db->execute(
            sql: '
                            UPDATE auth_user
                            SET email=?,
                                phone=?,
                                first_name=?,
                                last_name=?,
                                language=?,
                                active=?
                            WHERE id=?
                        ',
            parameters: [
                $email,
                $phone,
                $firstName,
                $lastName,
                $languageCode,
                $active ? 1 : 0,
                $id,
            ],
        );
    }

    /**
     * Counts a password attempt atomically, only below the limit: `false` if the limit is reached (locked out). The
     * counter is reset only by a new password (`setPassword()`, `removePassword()`), not by a successful login.
     */
    public function registerWrongPasswordAttempt(int $id, int $maxAllowedWrongPasswordAttempts): bool
    {
        return $this->db->execute(
            sql: 'UPDATE auth_user SET wrong_login_attempts=wrong_login_attempts+1 WHERE id=? AND wrong_login_attempts<?',
            parameters: [$id, $maxAllowedWrongPasswordAttempts],
        )->rowCount() === 1;
    }

    /**
     * Gives back the attempt counted by `registerWrongPasswordAttempt()` after the right password.
     */
    public function releaseWrongPasswordAttempt(int $id): void
    {
        $this->db->execute(
            sql: 'UPDATE auth_user SET wrong_login_attempts=wrong_login_attempts-1 WHERE id=? AND wrong_login_attempts>0',
            parameters: [$id],
        );
    }

    /**
     * Stores an upgraded hash of the same password (lazy upgrade at login); keeps the wrong login attempts.
     */
    public function updatePasswordHash(int $id, Password $password): void
    {
        $this->db->execute(
            sql: 'UPDATE auth_user SET password_salt=?, password_hash=? WHERE id=?',
            parameters: [$password->salt, $password->hash, $id],
        );
    }

    public function setPassword(
        int $id,
        Password $newPassword,
    ): void {
        $this->db->execute(
            sql: 'UPDATE auth_user SET password_salt=?, password_hash=?, wrong_login_attempts=0 WHERE id=?',
            parameters: [
                $newPassword->salt,
                $newPassword->hash,
                $id,
            ],
        );
    }

    public function removePassword(int $id): void
    {
        $this->db->execute(
            sql: 'UPDATE auth_user SET password_salt=?, password_hash=?, wrong_login_attempts=0 WHERE id=?',
            parameters: [
                null,
                null,
                $id,
            ],
        );
    }
}
