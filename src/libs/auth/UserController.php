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
    public function deleteUser(int $userID): void
    {
        $repositories = $this->repositories;
        $db = $repositories->db();
        $db->beginTransaction();
        try {
            $repositories->logins()->unsetUserID(userID: $userID);
            $repositories->sessions()->deleteByUserID(userID: $userID);
            $repositories->tokens()->deleteByUserID(userID: $userID);
            $repositories->userGroups()->deleteByUserID(userID: $userID);
            $repositories->ipWhitelists()->deleteByUserID(userID: $userID);
            $repositories->apiKeys()->deleteByUserID(userID: $userID);
            $this->userDeleteHandler?->beforeDeleteUser(userID: $userID);
            $repositories->users()->delete(ID: $userID);
            $db->commit();
        } catch (Throwable $throwable) {
            $db->rollBack();
            throw $throwable;
        }
        if ($this->currentUser?->id === $userID) {
            $this->authSession->logOut();
        }
    }
}
