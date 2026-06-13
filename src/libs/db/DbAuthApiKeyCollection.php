<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

class DbAuthApiKeyCollection
{
    /** @var DbAuthApiKey[] $items */
    private(set) array $items = [];

    public function __construct()
    {
    }

    public function add(DbAuthApiKey $dbAuthApiKey): void
    {
        $this->items[$dbAuthApiKey->publicID] = $dbAuthApiKey;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function getFirst(): DbAuthApiKey
    {
        return current(array: $this->items);
    }
}