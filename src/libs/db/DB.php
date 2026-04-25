<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\ActraBackend;
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
}