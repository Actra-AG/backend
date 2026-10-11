<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\libs\auth\UserDeleteHandler;
use actra\backend\libs\db\BackendRepositories;
use Override;
use RuntimeException;

/**
 * Records the calls of `UserController::deleteUser()`; with `fail: true` it throws after recording.
 */
final class RecordingUserDeleteHandler implements UserDeleteHandler
{
    public private(set) ?int $userId = null;
    public private(set) ?BackendRepositories $repositories = null;
    public private(set) bool $wasInTransaction = false;

    public function __construct(private readonly bool $fail = false) {}

    #[Override]
    public function beforeDeleteUser(int $userId, BackendRepositories $repositories): void
    {
        $this->userId = $userId;
        $this->repositories = $repositories;
        $this->wasInTransaction = $repositories->db()->inTransaction();
        if ($this->fail) {
            throw new RuntimeException(message: 'Handler failed.');
        }
    }
}
