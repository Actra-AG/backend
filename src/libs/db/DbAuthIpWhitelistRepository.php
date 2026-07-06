<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

class DbAuthIpWhitelistRepository
{
    public static function insert(
        int $userID,
        string $ipAddress
    ): void {
        DB::get()->execute(
            sql: 'INSERT INTO auth_ipWhitelist (userID, ipAddress) VALUES (?, ?)',
            parameters: [
                $userID,
                $ipAddress,
            ]
        );
    }

    public static function delete(
        int $userID,
        string $ipAddress
    ): void {
        DB::get()->execute(
            sql: 'DELETE FROM auth_ipWhitelist WHERE userID=? AND ipAddress=?',
            parameters: [
                $userID,
                $ipAddress,
            ]
        );
    }

    public static function deleteByUserID(int $userID): void
    {
        DB::get()->execute(
            sql: 'DELETE FROM auth_ipWhitelist WHERE userID=?',
            parameters: [
                $userID,
            ]
        );
    }
}