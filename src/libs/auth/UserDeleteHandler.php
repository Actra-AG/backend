<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\libs\db\BackendRepositories;

/**
 * Deletes the project's data of a user inside the delete transaction of `UserController::deleteUser()`.
 */
interface UserDeleteHandler
{
    /**
     * @param BackendRepositories $repositories The repositories of the running delete transaction: run every query
     *                                          on `$repositories->db()` (or project repositories built from it)
     */
    public function beforeDeleteUser(int $userId, BackendRepositories $repositories): void;
}
