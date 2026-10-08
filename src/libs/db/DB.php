<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\ActraBackend;
use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbRow;
use actra\yuf\db\DbSettings;
use actra\yuf\db\FrameworkDb;
use LogicException;
use PDOException;

class DB extends FrameworkDb
{
    private static ?DB $instance = null;

    public static function get(): DB
    {
        if (DB::$instance !== null) {
            return DB::$instance;
        }
        return DB::$instance = new DB(
            connectionParameters: DbConnectionParameters::forMysql(dbSettings: ActraBackend::get()->dbSettings),
        );
    }

    /**
     * Makes DB::get() return a connection created from the given settings (e.g. a test database in integration
     * tests, where ActraBackend::init() is not called). Must be called before the first DB::get().
     *
     * @throws LogicException if the connection has already been created
     * @throws PDOException if the database cannot be reached
     */
    public static function useConnection(DbSettings $dbSettings): DB
    {
        if (DB::$instance !== null) {
            throw new LogicException(message: 'The database connection has already been created.');
        }
        return DB::$instance = new DB(connectionParameters: DbConnectionParameters::forMysql(dbSettings: $dbSettings));
    }

    /**
     * @return list<DbRow>
     */
    public function selectRowsFromQuery(DbQuery $dbQuery, int $offset, int $rowCount): array
    {
        $dbQueryData = $dbQuery->getDbQueryData(offset: $offset, rowCount: $rowCount);

        return $this->selectRows(
            sql: $dbQueryData->query,
            parameters: $dbQueryData->params,
        );
    }
}
