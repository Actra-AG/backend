<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\db;

use actra\backend\libs\db\BackendRepositories;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\yuf\clock\SystemClock;
use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbQueryLogList;
use actra\yuf\db\FrameworkDb;
use ArrayObject;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BackendRepositoriesTest extends TestCase
{
    public function testNoConnectionIsOpenedBeforeARepositoryIsUsed(): void
    {
        $repositories = new BackendRepositories(
            connect: static fn(): FrameworkDb => throw new LogicException(message: 'Connected too early.'),
        );

        $this->expectException(LogicException::class);

        $repositories->users();
    }

    public function testRepositoriesAreCreatedOnce(): void
    {
        $repositories = new BackendRepositories(
            connect: static fn(): FrameworkDb => throw new LogicException(message: 'Not used.'),
        );

        $this->assertSame($repositories->userLogins(), $repositories->userLogins());
    }

    /**
     * The backend uses the connection of the project (`ActraBackend::create(connectDb:)`): one connection per request,
     * opened on first use.
     */
    public function testBackendSharesTheConnectionOfTheProject(): void
    {
        $db = new FrameworkDb(connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'), queryLog: new DbQueryLogList(clock: new SystemClock()));
        $connections = new ArrayObject();
        $actraBackend = ActraBackendTestInstance::create(
            connectDb: static function () use ($db, $connections): FrameworkDb {
                $connections->append($db);

                return $db;
            },
        );

        $this->assertCount(0, $connections);
        $this->assertSame($db, $actraBackend->getRepositories()->db());
        $this->assertSame($db, BackendRepositories::fromDb(db: $db)->db());
        $actraBackend->getRepositories()->users();
        $this->assertCount(1, $connections);
    }
}
