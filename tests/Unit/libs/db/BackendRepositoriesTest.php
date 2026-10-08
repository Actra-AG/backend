<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\db;

use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\DB;
use LogicException;
use PHPUnit\Framework\TestCase;

final class BackendRepositoriesTest extends TestCase
{
    public function testNoConnectionIsOpenedBeforeARepositoryIsUsed(): void
    {
        $repositories = new BackendRepositories(
            connect: static fn(): DB => throw new LogicException(message: 'Connected too early.'),
        );

        $this->expectException(LogicException::class);

        $repositories->users();
    }

    public function testRepositoriesAreCreatedOnce(): void
    {
        $repositories = new BackendRepositories(
            connect: static fn(): DB => throw new LogicException(message: 'Not used.'),
        );

        $this->assertSame($repositories->userLogins(), $repositories->userLogins());
    }
}
