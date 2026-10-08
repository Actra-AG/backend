<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AuthResultEnum;

final class DbAuthLoginRepository
{
    public function __construct(private readonly DB $db) {}

    public function insert(
        ?int $userId,
        string $sessionId,
        string $ipAddress,
        string $inputEmail,
        AuthResultEnum $authResult,
    ): void {
        $this->db->execute(
            sql: '
                INSERT INTO auth_login
                SET user_id=?,
                    session_id=?,
                    ip_address=?,
                    email=?,
                    result=?
            ',
            parameters: [
                $userId,
                $sessionId,
                $ipAddress,
                $inputEmail,
                $authResult->value,
            ],
        );
    }

    public function unsetUserId(int $userId): void
    {
        $this->db->execute(
            sql: '
                UPDATE auth_login SET user_id=NULL WHERE user_id=?
            ',
            parameters: [
                $userId,
            ],
        );
    }
}
