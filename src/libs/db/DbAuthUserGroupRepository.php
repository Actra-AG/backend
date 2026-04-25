<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

class DbAuthUserGroupRepository
{
    public static function insert(
        int $userID,
        int $groupID
    ): void {
        DB::get()->execute(
            sql: '
				INSERT INTO auth_user_group
				SET userID=?,
				    groupID=?
			',
            parameters: [
                $userID,
                $groupID,
            ]
        );
    }

    public static function delete(
        int $userID,
        int $groupID
    ): void {
        DB::get()->execute(
            sql: '
				DELETE FROM auth_user_group
				WHERE userID=?
				  AND groupID=?
			',
            parameters: [
                $userID,
                $groupID,
            ]
        );
    }

    public static function deleteByUserID(int $userID): void
    {
        DB::get()->execute(
            sql: '
                DELETE FROM auth_user_group
                WHERE ID>0
                  AND userID=?
            ',
            parameters: [
                $userID,
            ]
        );
    }
}