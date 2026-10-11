<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\auth;

use actra\backend\libs\auth\UserController;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\tests\Double\RecordingUserDeleteHandler;
use actra\backend\tests\Double\TestDatabase;
use actra\backend\tests\Double\TestUsers;
use actra\yuf\auth\AuthSession;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The `UserDeleteHandler` of the project runs inside the delete transaction, on the repositories of the controller.
 */
final class UserControllerTest extends TestCase
{
    private BackendRepositories $repositories;
    private TestUsers $testUsers;

    #[\Override]
    protected function setUp(): void
    {
        $this->repositories = BackendRepositories::fromDb(db: TestDatabase::connect());
        $this->testUsers = new TestUsers(db: $this->repositories->db());
    }

    private function createController(RecordingUserDeleteHandler $handler): UserController
    {
        return new UserController(
            repositories: $this->repositories,
            userDeleteHandler: $handler,
            authSession: new AuthSession(session: new Session(storage: new ArraySessionStorage())),
            currentUser: null,
        );
    }

    private function userExists(int $userId): bool
    {
        return $this->repositories->db()->selectRow(
            sql: 'SELECT id FROM auth_user WHERE id=?',
            parameters: [$userId],
        ) !== null;
    }

    public function testHandlerGetsTheUserIdAndTheRepositoriesOfTheTransaction(): void
    {
        $userId = $this->testUsers->create(email: 'user-controller-delete@example.invalid');
        $handler = new RecordingUserDeleteHandler();

        $this->createController(handler: $handler)->deleteUser(userId: $userId);

        $this->assertSame($userId, $handler->userId);
        $this->assertSame($this->repositories, $handler->repositories);
        $this->assertTrue($handler->wasInTransaction);
        $this->assertFalse($this->userExists(userId: $userId));
    }

    public function testExceptionOfTheHandlerRollsBackAndKeepsTheUser(): void
    {
        $userId = $this->testUsers->create(email: 'user-controller-rollback@example.invalid');
        $handler = new RecordingUserDeleteHandler(fail: true);

        try {
            $this->createController(handler: $handler)->deleteUser(userId: $userId);
            UserControllerTest::fail('The exception of the handler was not rethrown.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Handler failed.', $runtimeException->getMessage());
        }

        $this->assertFalse($this->repositories->db()->inTransaction());
        $this->assertTrue($this->userExists(userId: $userId));
        $this->assertSame(
            1,
            $this->repositories->db()->selectRow(
                sql: 'SELECT COUNT(*) AS amount FROM auth_user_group WHERE user_id=?',
                parameters: [$userId],
            )?->getInt(column: 'amount'),
        );
    }
}
