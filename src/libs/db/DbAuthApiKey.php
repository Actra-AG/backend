<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\Password;

readonly class DbAuthApiKey
{
    public function __construct(
        public int $userID,
        public string $publicID,
        public Password $key
    ) {
    }
}