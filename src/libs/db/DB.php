<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\ActraBackend;
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbRow;
use actra\yuf\db\FrameworkDB;

class DB extends FrameworkDB
{
    private static ?DB $instance = null;

    public static function get(): DB
    {
        if (DB::$instance !== null) {
            return DB::$instance;
        }
        return DB::$instance = new DB(dbSettingsModel: ActraBackend::get()->dbSettingsModel);
    }

    /**
     * @return list<DbRow>
     */
    public function selectRowsFromQuery(DbQuery $dbQuery, int $offset, int $rowCount): array
    {
        $dbQueryData = $dbQuery->getDbQueryData(offset: $offset, rowCount: $rowCount);

        return $this->selectRows(
            sql: $dbQueryData->query,
            parameters: array_values(array: $dbQueryData->params)
        );
    }
}