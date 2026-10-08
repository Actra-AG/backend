<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use Closure;

/**
 * The repositories of the backend on one database connection, which is opened on first use.
 */
final class BackendRepositories
{
    private ?DB $db = null;
    private ?DbAuthApiKeyRepository $apiKeys = null;
    private ?DbAuthGroupRepository $groups = null;
    private ?DbAuthIpWhitelistRepository $ipWhitelists = null;
    private ?DbAuthLoginRepository $logins = null;
    private ?DbAuthSessionRepository $sessions = null;
    private ?DbAuthTokenRepository $tokens = null;
    private ?DbAuthUserGroupRepository $userGroups = null;
    private ?DbAuthUserLoginRepository $userLogins = null;
    private ?DbAuthUserNotificationRecipientRepository $notificationRecipients = null;
    private ?DbAuthUserNotificationRepository $notifications = null;
    private ?DbAuthUserRepository $users = null;

    /**
     * @param Closure(): DB $connect
     */
    public function __construct(private readonly Closure $connect) {}

    public static function fromDb(DB $db): BackendRepositories
    {
        return new BackendRepositories(connect: static fn(): DB => $db);
    }

    public function db(): DB
    {
        return $this->db ??= ($this->connect)();
    }

    public function apiKeys(): DbAuthApiKeyRepository
    {
        return $this->apiKeys ??= new DbAuthApiKeyRepository(db: $this->db());
    }

    public function groups(): DbAuthGroupRepository
    {
        return $this->groups ??= new DbAuthGroupRepository(db: $this->db());
    }

    public function ipWhitelists(): DbAuthIpWhitelistRepository
    {
        return $this->ipWhitelists ??= new DbAuthIpWhitelistRepository(db: $this->db());
    }

    public function logins(): DbAuthLoginRepository
    {
        return $this->logins ??= new DbAuthLoginRepository(db: $this->db());
    }

    public function sessions(): DbAuthSessionRepository
    {
        return $this->sessions ??= new DbAuthSessionRepository(db: $this->db());
    }

    public function tokens(): DbAuthTokenRepository
    {
        return $this->tokens ??= new DbAuthTokenRepository(db: $this->db());
    }

    public function userGroups(): DbAuthUserGroupRepository
    {
        return $this->userGroups ??= new DbAuthUserGroupRepository(db: $this->db());
    }

    public function userLogins(): DbAuthUserLoginRepository
    {
        return $this->userLogins ??= new DbAuthUserLoginRepository();
    }

    public function notificationRecipients(): DbAuthUserNotificationRecipientRepository
    {
        return $this->notificationRecipients ??= new DbAuthUserNotificationRecipientRepository(db: $this->db());
    }

    public function notifications(): DbAuthUserNotificationRepository
    {
        return $this->notifications ??= new DbAuthUserNotificationRepository(db: $this->db());
    }

    public function users(): DbAuthUserRepository
    {
        return $this->users ??= new DbAuthUserRepository(db: $this->db());
    }
}
