<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\DbAuthGroupCollection;
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\db\DbQuery;
use actra\yuf\exception\NotFoundException;

/**
 * Applies the `UserScope` of the project for the logged-in user of a request (`BackendViewContext::$userScopeGuard`).
 * Without scope, without login or for an actor that `UserScope::restrictsActor()` does not restrict, every method
 * leaves queries and groups unchanged.
 */
final class UserScopeGuard
{
    private ?bool $isRestricted = null;

    public function __construct(
        private readonly ?UserScope $userScope,
        private readonly ?MyAuthUser $actor,
        private readonly BackendRepositories $repositories,
    ) {}

    /**
     * Whether the scope restricts the logged-in user (asked once per request).
     */
    public function isRestricted(): bool
    {
        if ($this->isRestricted === null) {
            $this->isRestricted = $this->userScope !== null
                && $this->actor !== null
                && $this->userScope->restrictsActor(actor: $this->actor);
        }

        return $this->isRestricted;
    }

    /**
     * Restricts a query of users or of rows of users (`UserScope::restrictUserQuery()`).
     *
     * @param string $userIdColumn The column with the user ID in this query, e.g. `auth_users.id`
     */
    public function restrictUserQuery(DbQuery $dbQuery, string $userIdColumn): void
    {
        if ($this->userScope !== null && $this->actor !== null && $this->isRestricted()) {
            $this->userScope->restrictUserQuery(dbQuery: $dbQuery, userIdColumn: $userIdColumn, actor: $this->actor);
        }
    }

    /**
     * The user with this ID, if the logged-in user may manage it.
     *
     * @throws NotFoundException for an unknown user and for a user outside the scope alike
     */
    public function requireUser(int $userId): DbAuthUser
    {
        $dbAuthUser = $this->repositories->users()->selectById(id: $userId);
        if ($dbAuthUser === null || !$this->containsUser(userId: $userId)) {
            throw new NotFoundException();
        }

        return $dbAuthUser;
    }

    /**
     * Whether the logged-in user may see and manage the user with this ID (`true` without restriction).
     */
    public function containsUser(int $userId): bool
    {
        if ($this->userScope === null || $this->actor === null || !$this->isRestricted()) {
            return true;
        }

        return $this->userScope->containsUser(userId: $userId, actor: $this->actor);
    }

    /**
     * The groups the logged-in user may see, filter by and assign.
     */
    public function filterGroups(DbAuthGroupCollection $groups): DbAuthGroupCollection
    {
        if ($this->userScope === null || $this->actor === null || !$this->isRestricted()) {
            return $groups;
        }
        $allowedGroups = $this->userScope->filterGroups(groups: $groups, actor: $this->actor);
        // Never more than the given groups, whatever the project returns
        $filteredGroups = new DbAuthGroupCollection();
        foreach ($allowedGroups->items as $dbAuthGroup) {
            if (array_key_exists(key: $dbAuthGroup->id, array: $groups->items)) {
                $filteredGroups->add(dbAuthGroup: $dbAuthGroup);
            }
        }

        return $filteredGroups;
    }

    /**
     * Links a new user to the scope; call it inside the transaction that creates the user.
     *
     * @param BackendRepositories $repositories The repositories of the running transaction
     */
    public function afterUserCreated(int $userId, BackendRepositories $repositories): void
    {
        if ($this->userScope !== null && $this->actor !== null && $this->isRestricted()) {
            $this->userScope->afterUserCreated(userId: $userId, actor: $this->actor, repositories: $repositories);
        }
    }

    /**
     * The access rights of the logged-in user for the navigation: a restricted user loses the right to view the sent
     * emails, a log of the whole installation without user column.
     */
    public function filterAccessRights(AccessRightCollection $accessRightCollection): AccessRightCollection
    {
        if (!$this->isRestricted()) {
            return $accessRightCollection;
        }

        return AccessRightCollection::createFromStringArray(input: array_values(array: array_filter(
            array: $accessRightCollection->listAccessRights(),
            callback: static fn(string $accessRight): bool => $accessRight !== ActraBackend::RIGHT_VIEW_SENT_EMAILS,
        )));
    }
}
