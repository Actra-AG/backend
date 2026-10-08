<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbRow;

class DbAuthGroupRepository
{
    public const string SELECT_QUERY = '
		SELECT auth_group.ID,
		       auth_group.title
		FROM auth_group
	';
    private static ?DbAuthGroupCollection $cache = null;

    public static function selectByID(int $ID): ?DbAuthGroup
    {
        return array_find(
            array: DbAuthGroupRepository::listAll()->items,
            callback: fn($dbAuthGroup) => $ID === $dbAuthGroup->ID,
        );
    }

    public static function listAll(): DbAuthGroupCollection
    {
        if (DbAuthGroupRepository::$cache === null) {
            DbAuthGroupRepository::$cache = DbAuthGroupRepository::listByCond(
                whereCond: '',
                parameters: [],
            );
        }

        return DbAuthGroupRepository::$cache;
    }

    /**
     * @param list<int> $parameters
     */
    private static function listByCond(string $whereCond, array $parameters): DbAuthGroupCollection
    {
        $dbAuthGroupCollection = new DbAuthGroupCollection();
        foreach (
            DB::get()->selectRows(
                sql: DbAuthGroupRepository::SELECT_QUERY . $whereCond . ' ORDER BY auth_group.title',
                parameters: $parameters,
            ) as $row
        ) {
            $dbAuthGroupCollection->add(dbAuthGroup: DbAuthGroupRepository::createDbAuthGroup(row: $row));
        }

        return $dbAuthGroupCollection;
    }

    private static function createDbAuthGroup(DbRow $row): DbAuthGroup
    {
        return new DbAuthGroup(
            ID: $row->getInt(column: 'ID'),
            title: $row->getString(column: 'title'),
        );
    }

    public static function listByUserID(int $userID): ?DbAuthGroupCollection
    {
        return DbAuthGroupRepository::listByCond(
            whereCond: 'WHERE auth_group.ID IN (SELECT groupID FROM auth_user_group WHERE userID=?)',
            parameters: [
                $userID,
            ],
        );
    }
}
