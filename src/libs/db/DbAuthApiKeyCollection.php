<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use LogicException;

final class DbAuthApiKeyCollection
{
    /** @var DbAuthApiKey[] $items */
    public private(set) array $items = [];

    public function __construct() {}

    public function add(DbAuthApiKey $dbAuthApiKey): void
    {
        $this->items[$dbAuthApiKey->publicId] = $dbAuthApiKey;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function getFirst(): DbAuthApiKey
    {
        $first = current(array: $this->items);
        if ($first === false) {
            throw new LogicException(message: 'The collection is empty.');
        }

        return $first;
    }
}
