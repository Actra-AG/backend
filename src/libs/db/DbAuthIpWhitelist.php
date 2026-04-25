<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

readonly class DbAuthIpWhitelist
{
    public function __construct(
        public int $ID,
        public int $userID,
        public string $ipAddress
    ) {
    }
}