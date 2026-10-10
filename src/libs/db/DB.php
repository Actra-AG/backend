<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbSettings;
use actra\yuf\db\FrameworkDb;
use PDOException;

final class DB extends FrameworkDb
{
    /**
     * Connects to the database of the backend (MySQL / MariaDB).
     *
     * @throws PDOException if the database cannot be reached
     */
    public static function fromSettings(DbSettings $dbSettings): DB
    {
        return new DB(connectionParameters: DbConnectionParameters::forMysql(dbSettings: $dbSettings));
    }

}
