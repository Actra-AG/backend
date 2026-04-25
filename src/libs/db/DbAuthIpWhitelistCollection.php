<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\datacheck\validatorTypes\IpValidator;

class DbAuthIpWhitelistCollection
{
    /** @var DbAuthIpWhitelist[] $items */
    private(set) array $items = [];

    public function __construct()
    {
    }

    public function add(DbAuthIpWhitelist $dbAuthIpWhitelist): void
    {
        $this->items[$dbAuthIpWhitelist->ID] = $dbAuthIpWhitelist;
    }

    public function isEmpty(): bool
    {
        return count(value: $this->items) === 0;
    }

    public function check(
        string $ip,
        bool $returnTrueIfEmpty
    ): bool {
        if ($this->isEmpty()) {
            return $returnTrueIfEmpty;
        }
        return array_any(
            array: $this->items,
            callback: fn(DbAuthIpWhitelist $dbAuthIpWhitelist) => IpValidator::isInWhitelist(
                whiteList: [$dbAuthIpWhitelist->ipAddress],
                ipAddressToCheck: $ip
            )
        );
    }
}