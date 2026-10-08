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
use actra\yuf\db\DbQuery;

final class DbAuthTokenRepository
{
    public function __construct(private readonly DB $db) {}

    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
				        SELECT auth_token.user_id,
				               auth_token.registered,
				               auth_token.registered_client,
				               auth_token.type,
				               auth_token.claimed,
				               auth_token.claimed_client,
				               auth_token.token
				        FROM auth_token
				            INNER JOIN auth_user ON auth_user.id=auth_token.user_id
				    ',
        );
    }

    public function createToken(
        DbAuthUser $dbAuthUser,
        AuthTokenTypeEnum $authTokenTypeEnum,
        ClientData $clientData,
        Clock $clock = new SystemClock(),
    ): string {
        $token = strtoupper(
            string: StringUtils::randomString(
                requiredStringLength: 6,
                noSpecialChars: true,
            ),
        );
        $this->db->execute(
            sql: '
                INSERT into auth_token
                SET auth_token.user_id=?,
                    auth_token.type=?,
                    auth_token.token=?,
                    auth_token.registered=?,
                    auth_token.registered_client=?
            ',
            parameters: [
                $dbAuthUser->id,
                $authTokenTypeEnum->value,
                $token,
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $clientData->toJson(),
            ],
        );

        return $token;
    }

    public function getClaimable(
        AuthTokenTypeEnum $authTokenType,
        string $token,
        Clock $clock = new SystemClock(),
    ): ?DbAuthToken {
        $rows = $this->db->selectRows(
            sql: '
				SELECT auth_token.id,
				       auth_token.user_id,
				       auth_user.email
				FROM auth_token
				    INNER JOIN auth_user ON auth_token.user_id = auth_user.id
				WHERE auth_token.type=?
				  AND auth_token.token=?
				  AND auth_token.registered>=DATE_SUB(?, INTERVAL ? MINUTE)
				  AND auth_token.claimed IS NULL
				  AND auth_token.token=(SELECT last.token
				                        FROM auth_token last
				                        WHERE last.user_id=auth_token.user_id
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
            id: $row->getInt(column: 'id'),
            userId: $row->getInt(column: 'user_id'),
            email: $row->getString(column: 'email'),
        );
    }

    public function claim(DbAuthToken $dbAuthToken, ClientData $clientData, Clock $clock = new SystemClock()): void
    {
        $this->db->execute(
            sql: '
                UPDATE auth_token
                SET auth_token.claimed=?,
                    auth_token.claimed_client=?
                WHERE auth_token.id=?
            ',
            parameters: [
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $clientData->toJson(),
                $dbAuthToken->id,
            ],
        );
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->execute(
            sql: '
                DELETE FROM auth_token
                       WHERE user_id=?
            ',
            parameters: [
                $userId,
            ],
        );
    }
}
