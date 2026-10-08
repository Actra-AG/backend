<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

interface UserDeleteHandlerInterface
{
    public function beforeDeleteUser(int $userID): void;
}
