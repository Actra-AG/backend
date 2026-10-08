<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\StringUtils;
use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQuery;
use actra\yuf\session\AbstractSessionHandler;

class DbAuthTokenRepository
{
    public static function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
				        SELECT auth_token.userID,
				               auth_token.registered,
				               auth_token.registeredClient,
				               auth_token.type,
				               auth_token.claimed,
				               auth_token.claimedClient,
				               auth_token.token
				        FROM auth_token
				            INNER JOIN auth_user ON auth_user.ID=auth_token.userID
				    ',
        );
    }

    public static function createToken(
        DbAuthUser $dbAuthUser,
        AuthTokenTypeEnum $authTokenTypeEnum,
        Clock $clock = new SystemClock(),
    ): string {
        $token = strtoupper(
            string: StringUtils::randomString(
                requiredStringLength: 6,
                noSpecialChars: true,
            ),
        );
        DB::get()->execute(
            sql: '
                INSERT into auth_token
                SET auth_token.userID=?,
                    auth_token.type=?,
                    auth_token.token=?,
                    auth_token.registered=?,
                    auth_token.registeredClient=?
            ',
            parameters: [
                $dbAuthUser->ID,
                $authTokenTypeEnum->value,
                $token,
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                DbAuthTokenRepository::getClientData(),
            ],
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
        string $token,
        Clock $clock = new SystemClock(),
    ): ?DbAuthToken {
        $rows = DB::get()->selectRows(
            sql: '
				SELECT auth_token.ID,
				       auth_token.userID,
				       auth_user.email
				FROM auth_token
				    INNER JOIN auth_user ON auth_token.userID = auth_user.ID
				WHERE auth_token.type=?
				  AND auth_token.token=?
				  AND auth_token.registered>=DATE_SUB(?, INTERVAL ? MINUTE)
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
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $authTokenType->getExpirationInMinutes(),
            ],
        );
        if (count(value: $rows) !== 1) {
            return null;
        }
        $row = $rows[0];

        return new DbAuthToken(
            ID: $row->getInt(column: 'ID'),
            userID: $row->getInt(column: 'userID'),
            email: $row->getString(column: 'email'),
        );
    }

    public static function claim(DbAuthToken $dbAuthToken, Clock $clock = new SystemClock()): void
    {
        DB::get()->execute(
            sql: '
                UPDATE auth_token
                SET auth_token.claimed=?,
                    auth_token.claimedClient=?
                WHERE auth_token.ID=?
            ',
            parameters: [
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                DbAuthTokenRepository::getClientData(),
                $dbAuthToken->ID,
            ],
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
            ],
        );
    }
}
