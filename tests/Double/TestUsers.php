<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\libs\db\DB;
use actra\yuf\auth\Password;

/**
 * Users in the `TestDatabase`: each test creates its own (unique email address).
 */
final readonly class TestUsers
{
    public function __construct(private DB $db) {}

    /**
     * A user of the group "Administrator" of `db/data.sql` (backend access) unless `withRights: false`.
     *
     * @param list<string> $ipWhitelist
     */
    public function create(
        string $email,
        ?string $password = null,
        bool $isActive = true,
        bool $withRights = true,
        array $ipWhitelist = [],
        int $wrongLoginAttempts = 0,
    ): int {
        $hash = $password === null ? null : Password::generateNew(rawPassword: $password);
        $this->db->execute(
            sql: '
                INSERT INTO auth_user
                SET email=?, phone=\'\', first_name=\'Test\', last_name=\'User\', active=?, password_salt=?,
                    password_hash=?, wrong_login_attempts=?
            ',
            parameters: [$email, $isActive ? 1 : 0, $hash?->salt, $hash?->hash, $wrongLoginAttempts],
        );
        $userId = $this->db->getLastInsertId();
        if ($withRights) {
            $this->db->execute(sql: 'INSERT INTO auth_user_group SET user_id=?, group_id=1', parameters: [$userId]);
        }
        foreach ($ipWhitelist as $ipAddress) {
            $this->db->execute(
                sql: 'INSERT INTO auth_ip_whitelist SET user_id=?, ip_address=?',
                parameters: [$userId, $ipAddress],
            );
        }

        return $userId;
    }

    public function getWrongLoginAttempts(string $email): int
    {
        return $this->db->selectRow(
            sql: 'SELECT wrong_login_attempts FROM auth_user WHERE email=?',
            parameters: [$email],
        )?->getInt(column: 'wrong_login_attempts') ?? -1;
    }

    /**
     * The results (`AuthResultEnum` values) logged for the email address, oldest first.
     *
     * @return list<int>
     */
    public function listLoggedResults(string $email): array
    {
        return array_map(
            callback: static fn($row): int => $row->getInt(column: 'result'),
            array: $this->db->selectRows(sql: 'SELECT result FROM auth_login WHERE email=? ORDER BY id', parameters: [$email]),
        );
    }

    public function countTokens(string $email): int
    {
        return $this->db->selectRow(
            sql: 'SELECT COUNT(*) AS amount FROM auth_token WHERE user_id=(SELECT id FROM auth_user WHERE email=?)',
            parameters: [$email],
        )?->getInt(column: 'amount') ?? 0;
    }
}
