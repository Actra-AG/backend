<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\db;

use actra\backend\libs\db\DB;
use actra\yuf\db\DbSettings;
use LogicException;
use PDOException;
use PHPUnit\Framework\TestCase;

final class DBTest extends TestCase
{
    /**
     * Needs a reachable database (defaults: DDEV). Override with the environment variables
     * TEST_DB_HOST, TEST_DB_NAME, TEST_DB_USER and TEST_DB_PASSWORD. Skipped if there is none.
     * The static instance cannot be reset, so the whole behaviour is checked in one test.
     */
    public function testUseConnectionSetsTheInstanceReturnedByGetOnlyOnce(): void
    {
        $dbSettings = new DbSettings(
            hostName: $this->env(name: 'TEST_DB_HOST', default: 'db'),
            databaseName: $this->env(name: 'TEST_DB_NAME', default: 'db'),
            userName: $this->env(name: 'TEST_DB_USER', default: 'db'),
            password: $this->env(name: 'TEST_DB_PASSWORD', default: 'db'),
        );

        try {
            $db = DB::useConnection(dbSettings: $dbSettings);
        } catch (PDOException $pdoException) {
            DBTest::markTestSkipped('No test database available: ' . $pdoException->getMessage());
        }

        $this->assertSame($db, DB::get());

        $this->expectException(LogicException::class);
        DB::useConnection(dbSettings: $dbSettings);
    }

    private function env(string $name, string $default): string
    {
        $value = getenv(name: $name);

        return is_string(value: $value) ? $value : $default;
    }
}
