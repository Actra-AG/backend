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
        int $userID,
        int $groupID,
    ): void {
        $this->db->execute(
            sql: '
				INSERT INTO auth_user_group
				SET userID=?,
				    groupID=?
			',
            parameters: [
                $userID,
                $groupID,
            ],
        );
    }

    public function delete(
        int $userID,
        int $groupID,
    ): void {
        $this->db->execute(
            sql: '
				DELETE FROM auth_user_group
				WHERE userID=?
				  AND groupID=?
			',
            parameters: [
                $userID,
                $groupID,
            ],
        );
    }

    public function deleteByUserID(int $userID): void
    {
        $this->db->execute(
            sql: '
                DELETE FROM auth_user_group
                WHERE ID>0
                  AND userID=?
            ',
            parameters: [
                $userID,
            ],
        );
    }
}
