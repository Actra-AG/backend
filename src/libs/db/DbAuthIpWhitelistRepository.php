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

    public function listForUserId(int $userId): DbAuthIpWhitelistCollection
    {
        $dbAuthIpWhitelistCollection = new DbAuthIpWhitelistCollection();
        foreach (
            $this->db->selectRows(
                sql: '
                   SELECT id,
                          user_id,
                          ip_address
                   FROM auth_ip_whitelist
                   WHERE user_id=?
               ',
                parameters: [
                    $userId,
                ],
            ) as $row
        ) {
            $dbAuthIpWhitelistCollection->add(
                dbAuthIpWhitelist: new DbAuthIpWhitelist(
                    id: $row->getInt(column: 'id'),
                    userId: $row->getInt(column: 'user_id'),
                    ipAddress: $row->getString(column: 'ip_address'),
                ),
            );
        }

        return $dbAuthIpWhitelistCollection;
    }

    public function insert(
        int $userId,
        string $ipAddress,
    ): void {
        $this->db->execute(
            sql: 'INSERT INTO auth_ip_whitelist (user_id, ip_address) VALUES (?, ?)',
            parameters: [
                $userId,
                $ipAddress,
            ],
        );
    }

    public function delete(
        int $userId,
        string $ipAddress,
    ): void {
        $this->db->execute(
            sql: 'DELETE FROM auth_ip_whitelist WHERE user_id=? AND ip_address=?',
            parameters: [
                $userId,
                $ipAddress,
            ],
        );
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->execute(
            sql: 'DELETE FROM auth_ip_whitelist WHERE user_id=?',
            parameters: [
                $userId,
            ],
        );
    }
}
