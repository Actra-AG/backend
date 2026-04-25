<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use stdClass;

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
            callback: fn($dbAuthGroup) => $ID === $dbAuthGroup->ID
        );
    }

    public static function listAll(): DbAuthGroupCollection
    {
        if (is_null(value: DbAuthGroupRepository::$cache)) {
            DbAuthGroupRepository::$cache = DbAuthGroupRepository::listByCond(
                whereCond: '',
                parameters: []
            );
        }

        return DbAuthGroupRepository::$cache;
    }

    private static function listByCond(string $whereCond, array $parameters): DbAuthGroupCollection
    {
        $dbAuthGroupCollection = new DbAuthGroupCollection();
        foreach (
            DB::get()->select(
                sql: DbAuthGroupRepository::SELECT_QUERY . $whereCond . ' ORDER BY auth_group.title',
                parameters: $parameters
            ) as $item
        ) {
            $dbAuthGroupCollection->add(dbAuthGroup: DbAuthGroupRepository::createDbAuthGroup(data: $item));
        }

        return $dbAuthGroupCollection;
    }

    private static function createDbAuthGroup(stdClass $data): DbAuthGroup
    {
        return new DbAuthGroup(
            ID: $data->ID,
            title: $data->title
        );
    }

    public static function listByUserID(int $userID): ?DbAuthGroupCollection
    {
        return DbAuthGroupRepository::listByCond(
            whereCond: 'WHERE auth_group.ID IN (SELECT groupID FROM auth_user_group WHERE userID=?)',
            parameters: [
                $userID,
            ]
        );
    }
}