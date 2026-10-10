<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\db\DbRow;

final class DbAuthGroupRepository
{
    public function __construct(private readonly DB $db) {}

    public const string SELECT_QUERY = '
		SELECT auth_group.id,
		       auth_group.title,
		       (SELECT GROUP_CONCAT(auth_group_right.right_name)
		           FROM auth_group_right
		           WHERE auth_group_right.group_id=auth_group.id) AS access_rights
		FROM auth_group
	';
    private ?DbAuthGroupCollection $cache = null;

    public function selectById(int $id): ?DbAuthGroup
    {
        return array_find(
            array: $this->listAll()->items,
            callback: fn($dbAuthGroup) => $id === $dbAuthGroup->id,
        );
    }

    public function listAll(): DbAuthGroupCollection
    {
        if ($this->cache === null) {
            $this->cache = $this->listByCond(
                whereCond: '',
                parameters: [],
            );
        }

        return $this->cache;
    }

    /**
     * @param list<int> $parameters
     */
    private function listByCond(string $whereCond, array $parameters): DbAuthGroupCollection
    {
        $dbAuthGroupCollection = new DbAuthGroupCollection();
        foreach (
            $this->db->selectRows(
                sql: DbAuthGroupRepository::SELECT_QUERY . $whereCond . ' ORDER BY auth_group.title',
                parameters: $parameters,
            ) as $row
        ) {
            $dbAuthGroupCollection->add(dbAuthGroup: $this->createDbAuthGroup(row: $row));
        }

        return $dbAuthGroupCollection;
    }

    private function createDbAuthGroup(DbRow $row): DbAuthGroup
    {
        return new DbAuthGroup(
            id: $row->getInt(column: 'id'),
            title: $row->getString(column: 'title'),
            accessRightCollection: AccessRightCollection::createFromStringArray(
                input: $row->getStringList(column: 'access_rights'),
            ),
        );
    }

    public function listByUserId(int $userId): DbAuthGroupCollection
    {
        return $this->listByCond(
            whereCond: 'WHERE auth_group.id IN (SELECT group_id FROM auth_user_group WHERE user_id=?)',
            parameters: [
                $userId,
            ],
        );
    }
}
