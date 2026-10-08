<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\libs\db\BackendRepositories;
use actra\yuf\auth\AuthSession;
use Throwable;

final readonly class UserController
{
    public function __construct(
        private BackendRepositories $repositories,
        private ?UserDeleteHandler $userDeleteHandler,
        private AuthSession $authSession,
        private ?MyAuthUser $currentUser,
    ) {}

    /**
     * Deletes a user with its sessions, tokens, groups, IP whitelist and API key; the `UserDeleteHandler` of the
     * project deletes its own data first. A user who deletes himself is logged out.
     */
    public function deleteUser(int $userId): void
    {
        $repositories = $this->repositories;
        $db = $repositories->db();
        $db->beginTransaction();
        try {
            $repositories->logins()->unsetUserId(userId: $userId);
            $repositories->sessions()->deleteByUserId(userId: $userId);
            $repositories->tokens()->deleteByUserId(userId: $userId);
            $repositories->userGroups()->deleteByUserId(userId: $userId);
            $repositories->ipWhitelists()->deleteByUserId(userId: $userId);
            $repositories->apiKeys()->deleteByUserId(userId: $userId);
            $this->userDeleteHandler?->beforeDeleteUser(userId: $userId);
            $repositories->users()->delete(id: $userId);
            $db->commit();
        } catch (Throwable $throwable) {
            $db->rollBack();
            throw $throwable;
        }
        if ($this->currentUser?->id === $userId) {
            $this->authSession->logOut();
        }
    }
}
