<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use LogicException;

final class DbAuthUserNotificationCollection
{
    /** @var DbAuthUserNotification[] $items */
    public private(set) array $items = [];

    public function __construct() {}

    public function add(DbAuthUserNotification $dbAuthUserNotification): void
    {
        $this->items[$dbAuthUserNotification->id] = $dbAuthUserNotification;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function first(): DbAuthUserNotification
    {
        $first = current(array: $this->items);
        if ($first === false) {
            throw new LogicException(message: 'The collection is empty.');
        }

        return $first;
    }
}
