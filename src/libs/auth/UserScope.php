<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\DbAuthGroupCollection;
use actra\yuf\db\DbQuery;

/**
 * Restricts the built-in user management to the users an actor may manage (`ActraBackendSettings::$userScope`), e.g.
 * the users of one tenant. The actor is the logged-in user (during a session change the impersonated user). Projects
 * build the settings per request, so an implementation may hold e.g. the tenant ID. See `docs/users.md`.
 */
interface UserScope
{
    /**
     * Whether the actor is restricted at all; called once per request. `false` (e.g. a platform administrator): the
     * backend calls no other method and manages all users.
     */
    public function restrictsActor(MyAuthUser $actor): bool;

    /**
     * Restricts a query of users or of rows of users to the users the actor may manage, with
     * `DbQuery::addWherePart()` and bound parameters only, e.g.
     * `$userIdColumn . ' IN (SELECT user_id FROM tenant_user WHERE tenant_id=?)'`. Rows without user (`NULL`, e.g.
     * logins with an unknown email address) must not match.
     *
     * @param string $userIdColumn The column with the user ID in this query, e.g. `auth_users.id` or
     *     `auth_logins.user_id`
     */
    public function restrictUserQuery(DbQuery $dbQuery, string $userIdColumn, MyAuthUser $actor): void;

    /**
     * Whether the actor may see and manage this user; `false` is answered like an unknown ID (404).
     */
    public function containsUser(int $userId, MyAuthUser $actor): bool;

    /**
     * The groups the actor may see, filter by and assign: a subset of `$groups`.
     */
    public function filterGroups(DbAuthGroupCollection $groups, MyAuthUser $actor): DbAuthGroupCollection;

    /**
     * Called in the transaction that creates a user, e.g. to link the user to the tenant. An exception rolls the
     * creation back.
     *
     * @param BackendRepositories $repositories The repositories of the running transaction: run every query on
     *     `$repositories->db()` (or project repositories built from it)
     */
    public function afterUserCreated(int $userId, MyAuthUser $actor, BackendRepositories $repositories): void;
}
