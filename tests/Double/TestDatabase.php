<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\libs\db\DB;
use actra\yuf\db\DbSettings;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * The database `test_backend` with `db/schema.sql` and `db/data.sql`, created once per test run. Needs a reachable
 * MariaDB/MySQL (defaults: DDEV; override with TEST_DB_HOST, TEST_DB_USER and TEST_DB_PASSWORD); tests are skipped
 * without one. The next test run drops it, so tests use their own email addresses instead of transactions (the
 * backend opens its own connection).
 */
final class TestDatabase
{
    private const string DATABASE_NAME = 'test_backend';
    private static bool $isCreated = false;

    public static function settings(): DbSettings
    {
        return new DbSettings(
            hostName: TestDatabase::env(name: 'TEST_DB_HOST', default: 'db'),
            databaseName: TestDatabase::DATABASE_NAME,
            userName: TestDatabase::env(name: 'TEST_DB_USER', default: 'db'),
            password: TestDatabase::env(name: 'TEST_DB_PASSWORD', default: 'db'),
        );
    }

    /**
     * Creates the database on first use; skips the test without a database.
     */
    public static function connect(): DB
    {
        try {
            TestDatabase::createOnce();

            return DB::fromSettings(dbSettings: TestDatabase::settings());
        } catch (PDOException $pdoException) {
            TestCase::markTestSkipped('No test database available: ' . $pdoException->getMessage());
        }
    }

    private static function createOnce(): void
    {
        if (TestDatabase::$isCreated) {
            return;
        }
        $settings = TestDatabase::settings();
        $pdo = new PDO(
            dsn: 'mysql:host=' . $settings->hostName . ';charset=utf8mb4',
            username: $settings->userName,
            password: $settings->password,
            options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->exec(statement: 'DROP DATABASE IF EXISTS ' . TestDatabase::DATABASE_NAME);
        $pdo->exec(statement: 'CREATE DATABASE ' . TestDatabase::DATABASE_NAME . ' CHARACTER SET utf8mb4');
        $pdo->exec(statement: 'USE ' . TestDatabase::DATABASE_NAME);
        foreach (['schema.sql', 'data.sql'] as $fileName) {
            $pdo->exec(statement: (string) file_get_contents(filename: __DIR__ . '/../../db/' . $fileName));
        }
        TestDatabase::$isCreated = true;
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv(name: $name);

        return is_string(value: $value) ? $value : $default;
    }
}
