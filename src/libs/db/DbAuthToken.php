<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

final readonly class DbAuthToken
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $email,
    ) {}
}
