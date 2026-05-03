<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

class DbAuthUserNotificationCollection
{
    /** @var DbAuthUserNotification[] $items */
    private(set) array $items = [];

    public function __construct()
    {
    }

    public function add(DbAuthUserNotification $dbAuthUserNotification): void
    {
        $this->items[$dbAuthUserNotification->ID] = $dbAuthUserNotification;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function first(): DbAuthUserNotification
    {
        return current(array: $this->items);
    }
}