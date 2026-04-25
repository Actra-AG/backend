<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

class DbAuthIpWhitelistRepository
{
    public static function listForUserId(int $userID): DbAuthIpWhitelistCollection
    {
        $dbAuthIpWhitelistCollection = new DbAuthIpWhitelistCollection();
        foreach (
            DB::get()->select(
                sql: '
                   SELECT ID,
                          userID,
                          ipAddress
                   FROM auth_ipWhitelist
                   WHERE userID=?
               ',
                parameters: [
                    $userID,
                ]
            ) as $item
        ) {
            $dbAuthIpWhitelistCollection->add(
                dbAuthIpWhitelist: new DbAuthIpWhitelist(
                    ID: $item->ID,
                    userID: $item->userID,
                    ipAddress: $item->ipAddress
                )
            );
        }

        return $dbAuthIpWhitelistCollection;
    }
    /*

    public static function insert(
        int $userID,
        string $ipAddress
    ): void {
        DB::get()->execute(
            sql: 'INSERT INTO ipWhitelist (userID, ipAddress) VALUES (?, ?)',
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
            sql: 'DELETE FROM ipWhitelist WHERE userID=? AND ipAddress=?',
            parameters: [
                $userID,
                $ipAddress,
            ]
        );
    }
    */
}