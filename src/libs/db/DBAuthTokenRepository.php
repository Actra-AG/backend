<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\common\StringUtils;
use actra\yuf\core\HttpRequest;
use actra\yuf\session\AbstractSessionHandler;

class DBAuthTokenRepository
{
    public static function createToken(
        DbAuthUser $dbAuthUser,
        AuthTokenTypeEnum $authTokenType
    ): string {
        $token = strtoupper(
            string: StringUtils::randomString(
                requiredStringLength: 6,
                noSpecialChars: true
            )
        );
        DB::get()->execute(
            sql: '
                INSERT into auth_token
                SET auth_token.userID=?,
                    auth_token.type=?,
                    auth_token.token=?,
                    auth_token.registeredClient=?
            ',
            parameters: [
                $dbAuthUser->ID,
                $authTokenType->value,
                $token,
                DBAuthTokenRepository::getClientData(),
            ]
        );

        return $token;
    }

    private static function getClientData(): string
    {
        return json_encode(value: [
            'userAgent' => HttpRequest::getUserAgent(),
            'ipAddress' => HttpRequest::getRemoteAddress(),
            'sessionId' => AbstractSessionHandler::getSessionHandler()->getID(),
        ]);
    }

    public static function getClaimable(
        AuthTokenTypeEnum $authTokenType,
        string $token
    ): ?DbAuthToken {
        $res = DB::get()->select(
            sql: '
				SELECT auth_token.ID,
				       auth_token.userID,
				       auth_user.email
				FROM auth_token
				    INNER JOIN auth_user ON auth_token.userID = auth_user.ID
				WHERE auth_token.type=?
				  AND auth_token.token=?
				  AND auth_token.registered>=DATE_SUB(NOW(), INTERVAL ? MINUTE)
				  AND auth_token.claimed IS NULL
				  AND auth_token.token=(SELECT last.token
				                        FROM auth_token last
				                        WHERE last.userID=auth_token.userID
				                          AND last.claimed IS NULL
				                        ORDER BY last.registered
				                        DESC LIMIT 1
				  )
			',
            parameters: [
                $authTokenType->value,
                $token,
                $authTokenType->getExpirationInMinutes(),
            ]
        );
        if ((count(value: $res) !== 1)) {
            return null;
        }
        $data = $res[0];

        return new DbAuthToken(
            ID: $data->ID,
            userID: $data->userID,
            email: $data->email
        );
    }

    public static function claim(DbAuthToken $dbAuthToken): void
    {
        DB::get()->execute(
            sql: '
                UPDATE auth_token
                SET auth_token.claimed=NOW(),
                    auth_token.claimedClient=?
                WHERE auth_token.ID=?
            ',
            parameters: [
                DBAuthTokenRepository::getClientData(),
                $dbAuthToken->ID,
            ]
        );
    }
    
    public static function deleteByUserID(int $userID): void
    {
        DB::get()->execute(
            sql: '
                DELETE FROM auth_token
                       WHERE userID=?
            ',
            parameters: [
                $userID,
            ]
        );
    }
}