<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

final class DbAuthUserGroupRepository
{
    public function __construct(private readonly DB $db) {}

    public function insert(
        int $userId,
        int $groupId,
    ): void {
        $this->db->execute(
            sql: '
				INSERT INTO auth_user_group
				SET user_id=?,
				    group_id=?
			',
            parameters: [
                $userId,
                $groupId,
            ],
        );
    }

    public function delete(
        int $userId,
        int $groupId,
    ): void {
        $this->db->execute(
            sql: '
				DELETE FROM auth_user_group
				WHERE user_id=?
				  AND group_id=?
			',
            parameters: [
                $userId,
                $groupId,
            ],
        );
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->execute(
            sql: '
                DELETE FROM auth_user_group
                WHERE id>0
                  AND user_id=?
            ',
            parameters: [
                $userId,
            ],
        );
    }
}
