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
        ?int $userID,
        string $sessionID,
        string $ipAddress,
        string $inputEmail,
        AuthResultEnum $authResult,
    ): void {
        $this->db->execute(
            sql: '
                INSERT INTO auth_login
                SET userID=?,
                    sessionId=?,
                    ipAddress=?,
                    email=?,
                    result=?
            ',
            parameters: [
                $userID,
                $sessionID,
                $ipAddress,
                $inputEmail,
                $authResult->value,
            ],
        );
    }

    public function unsetUserID(int $userID): void
    {
        $this->db->execute(
            sql: '
                UPDATE auth_login SET userID=NULL WHERE userID=?
            ',
            parameters: [
                $userID,
            ],
        );
    }
}
