<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

final class DbAuthIpWhitelistRepository
{
    public function __construct(private readonly DB $db) {}

    public function listForUserId(int $userID): DbAuthIpWhitelistCollection
    {
        $dbAuthIpWhitelistCollection = new DbAuthIpWhitelistCollection();
        foreach (
            $this->db->selectRows(
                sql: '
                   SELECT ID,
                          userID,
                          ipAddress
                   FROM auth_ipWhitelist
                   WHERE userID=?
               ',
                parameters: [
                    $userID,
                ],
            ) as $row
        ) {
            $dbAuthIpWhitelistCollection->add(
                dbAuthIpWhitelist: new DbAuthIpWhitelist(
                    ID: $row->getInt(column: 'ID'),
                    userID: $row->getInt(column: 'userID'),
                    ipAddress: $row->getString(column: 'ipAddress'),
                ),
            );
        }

        return $dbAuthIpWhitelistCollection;
    }

    public function insert(
        int $userID,
        string $ipAddress,
    ): void {
        $this->db->execute(
            sql: 'INSERT INTO auth_ipWhitelist (userID, ipAddress) VALUES (?, ?)',
            parameters: [
                $userID,
                $ipAddress,
            ],
        );
    }

    public function delete(
        int $userID,
        string $ipAddress,
    ): void {
        $this->db->execute(
            sql: 'DELETE FROM auth_ipWhitelist WHERE userID=? AND ipAddress=?',
            parameters: [
                $userID,
                $ipAddress,
            ],
        );
    }

    public function deleteByUserID(int $userID): void
    {
        $this->db->execute(
            sql: 'DELETE FROM auth_ipWhitelist WHERE userID=?',
            parameters: [
                $userID,
            ],
        );
    }
}
