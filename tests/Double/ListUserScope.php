<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\backend\libs\auth\MyAuthUser;
use actra\backend\libs\auth\UserScope;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\DbAuthGroupCollection;
use actra\yuf\db\DbQuery;
use RuntimeException;

/**
 * A scope like the one of a tenant: the users and groups of the lists, a new user joins the users.
 */
final class ListUserScope implements UserScope
{
    /**
     * @param list<int> $userIds The users of the scope
     * @param list<int> $groupIds The groups the actor may see and assign
     * @param bool $restricts `false`: the actor manages all users (e.g. a platform administrator)
     * @param bool $failsOnCreate `afterUserCreated()` throws, the creation must be rolled back
     */
    public function __construct(
        public array $userIds,
        private readonly array $groupIds,
        private readonly bool $restricts = true,
        private readonly bool $failsOnCreate = false,
    ) {}

    #[\Override]
    public function restrictsActor(MyAuthUser $actor): bool
    {
        return $this->restricts;
    }

    #[\Override]
    public function restrictUserQuery(DbQuery $dbQuery, string $userIdColumn, MyAuthUser $actor): void
    {
        if ($this->userIds === []) {
            $dbQuery->addWherePart(wherePart: '0=1', parameters: []);

            return;
        }
        $dbQuery->addWherePart(
            wherePart: $userIdColumn . ' IN (' . implode(separator: ',', array: array_fill(
                start_index: 0,
                count: count(value: $this->userIds),
                value: '?',
            )) . ')',
            parameters: $this->userIds,
        );
    }

    #[\Override]
    public function containsUser(int $userId, MyAuthUser $actor): bool
    {
        return in_array(needle: $userId, haystack: $this->userIds, strict: true);
    }

    #[\Override]
    public function filterGroups(DbAuthGroupCollection $groups, MyAuthUser $actor): DbAuthGroupCollection
    {
        $filteredGroups = new DbAuthGroupCollection();
        foreach ($groups->items as $dbAuthGroup) {
            if (in_array(needle: $dbAuthGroup->id, haystack: $this->groupIds, strict: true)) {
                $filteredGroups->add(dbAuthGroup: $dbAuthGroup);
            }
        }

        return $filteredGroups;
    }

    #[\Override]
    public function afterUserCreated(int $userId, MyAuthUser $actor, BackendRepositories $repositories): void
    {
        if ($this->failsOnCreate) {
            throw new RuntimeException(message: 'The link of the new user failed.');
        }
        $this->userIds[] = $userId;
    }
}
