<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AuthResult;

class DbAuthLoginRepository
{
    public static function insert(
        ?int $userID,
        string $sessionID,
        string $ipAddress,
        string $inputEmail,
        AuthResult $authResult
    ): void {
        DB::get()->execute(
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
            ]
        );
    }

    public static function unsetUserID(int $userID): void
    {
        DB::get()->execute(
            sql: '
                UPDATE auth_login SET userID=NULL WHERE userID=?
            ',
            parameters: [
                $userID,
            ]
        );
    }
}