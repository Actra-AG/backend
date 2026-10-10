<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\settings\AuthTokenTypeEnum;
use actra\backend\settings\TokenSendLimit;
use actra\yuf\auth\SecretTokenHash;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\StringUtils;
use actra\yuf\db\DbQuery;
use Throwable;

/**
 * The one-time tokens (login codes, password reset and activation links). Only the SHA-256 hash of a token is stored
 * (`token_hash`); the secret goes to the user by mail.
 */
final class DbAuthTokenRepository
{
    private const int LOGIN_CODE_LENGTH = 6;
    private const string LOGIN_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    // 22 letters and digits (about 131 bits): short enough for one line in text mails, no '-' or '_' that mail
    // clients break at or that ends a double-click selection
    private const int LINK_TOKEN_LENGTH = 22;
    private const string LINK_TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    private const string LINK_TOKEN_PATTERN = '/^[A-Za-z0-9]{22}$/D';

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
				               auth_token.claimed_client
				        FROM auth_token
				            INNER JOIN auth_user ON auth_user.id=auth_token.user_id
				    ',
        );
    }

    /**
     * Creates a token and returns its secret. A login code has 6 characters (it is only valid in the session that
     * requested it); password reset and activation links get 22 letters and digits (about 131 bits), because the
     * link works without that session.
     */
    public function createToken(
        DbAuthUser $dbAuthUser,
        AuthTokenTypeEnum $authTokenTypeEnum,
        ClientData $clientData,
        Clock $clock = new SystemClock(),
    ): string {
        $token = DbAuthTokenRepository::createSecret(authTokenType: $authTokenTypeEnum);
        $this->db->execute(
            sql: '
                INSERT into auth_token
                SET auth_token.user_id=?,
                    auth_token.type=?,
                    auth_token.token_hash=?,
                    auth_token.registered=?,
                    auth_token.registered_client=?
            ',
            parameters: [
                $dbAuthUser->id,
                $authTokenTypeEnum->value,
                SecretTokenHash::fromSecret(secret: $token)->hash,
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $clientData->toJson(),
            ],
        );

        return $token;
    }

    /**
     * Like `createToken()`, but only below the send limit: counting and inserting happen under a lock of the user row,
     * so parallel requests cannot exceed the limit. `null` if the limit is reached (nothing is created).
     */
    public function createTokenWithinLimit(
        DbAuthUser $dbAuthUser,
        AuthTokenTypeEnum $authTokenTypeEnum,
        ClientData $clientData,
        TokenSendLimit $tokenSendLimit,
        Clock $clock = new SystemClock(),
    ): ?string {
        $db = $this->db;
        $db->beginTransaction();
        try {
            $db->selectRow(sql: 'SELECT id FROM auth_user WHERE id=? FOR UPDATE', parameters: [$dbAuthUser->id]);
            $token = null;
            if (
                $this->countRegisteredWithin(
                    userId: $dbAuthUser->id,
                    authTokenType: $authTokenTypeEnum,
                    minutes: $tokenSendLimit->withinMinutes,
                    clock: $clock,
                ) < $tokenSendLimit->maxTokens
            ) {
                $token = $this->createToken(
                    dbAuthUser: $dbAuthUser,
                    authTokenTypeEnum: $authTokenTypeEnum,
                    clientData: $clientData,
                    clock: $clock,
                );
            }
            $db->commit();
        } catch (Throwable $throwable) {
            $db->rollBack();
            throw $throwable;
        }

        return $token;
    }

    private static function createSecret(AuthTokenTypeEnum $authTokenType): string
    {
        return match ($authTokenType) {
            AuthTokenTypeEnum::LOGIN => StringUtils::randomFromAlphabet(
                length: DbAuthTokenRepository::LOGIN_CODE_LENGTH,
                alphabet: DbAuthTokenRepository::LOGIN_CODE_ALPHABET,
            ),
            AuthTokenTypeEnum::PASSWORD, AuthTokenTypeEnum::ACTIVATION => StringUtils::randomFromAlphabet(
                length: DbAuthTokenRepository::LINK_TOKEN_LENGTH,
                alphabet: DbAuthTokenRepository::LINK_TOKEN_ALPHABET,
            ),
        };
    }

    /**
     * The tokens of a type registered for a user within the last minutes (claimed or not).
     */
    public function countRegisteredWithin(
        int $userId,
        AuthTokenTypeEnum $authTokenType,
        int $minutes,
        Clock $clock = new SystemClock(),
    ): int {
        return $this->db->selectRow(
            sql: '
				SELECT COUNT(*) AS amount
				FROM auth_token
				WHERE auth_token.user_id=?
				  AND auth_token.type=?
				  AND auth_token.registered>=DATE_SUB(?, INTERVAL ? MINUTE)
			',
            parameters: [
                $userId,
                $authTokenType->value,
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $minutes,
            ],
        )?->getInt(column: 'amount') ?? 0;
    }

    /**
     * The token if it is not claimed, not expired and the newest unclaimed token of its user, `null` otherwise.
     *
     * @param ?int $userId Only a token of this user (`null`: the user is not known yet, e.g. a password reset link)
     */
    public function getClaimable(
        AuthTokenTypeEnum $authTokenType,
        #[\SensitiveParameter]
        string $token,
        ?int $userId = null,
        Clock $clock = new SystemClock(),
    ): ?DbAuthToken {
        if (
            $token === ''
            || (
                $authTokenType !== AuthTokenTypeEnum::LOGIN
                && preg_match(pattern: DbAuthTokenRepository::LINK_TOKEN_PATTERN, subject: $token) !== 1
            )
        ) {
            return null;
        }
        $dbQuery = DbQuery::createFromSqlQuery(
            query: '
				SELECT auth_token.id,
				       auth_token.user_id,
				       auth_user.email
				FROM auth_token
				    INNER JOIN auth_user ON auth_token.user_id = auth_user.id
			',
        );
        $dbQuery->addWherePart(
            wherePart: 'auth_token.type=?
				  AND auth_token.token_hash=?
				  AND auth_token.registered>=DATE_SUB(?, INTERVAL ? MINUTE)
				  AND auth_token.claimed IS NULL
				  AND auth_token.id=(SELECT last.id
				                     FROM auth_token last
				                     WHERE last.user_id=auth_token.user_id
				                       AND last.claimed IS NULL
				                     ORDER BY last.registered DESC, last.id DESC
				                     LIMIT 1)',
            parameters: [
                $authTokenType->value,
                SecretTokenHash::fromSecret(secret: $token)->hash,
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $authTokenType->getExpirationInMinutes(),
            ],
        );
        if ($userId !== null) {
            $dbQuery->addWherePart(wherePart: 'auth_token.user_id=?', parameters: [$userId]);
        }
        $rows = $dbQuery->selectRowsFromDb(db: $this->db, offset: 0, rowCount: 2);
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

    /**
     * Marks the token as claimed, atomically: `false` if it was claimed before (e.g. by a parallel request), then it
     * must not be used.
     */
    public function claim(DbAuthToken $dbAuthToken, ClientData $clientData, Clock $clock = new SystemClock()): bool
    {
        return $this->db->execute(
            sql: '
                UPDATE auth_token
                SET auth_token.claimed=?,
                    auth_token.claimed_client=?
                WHERE auth_token.id=?
                  AND auth_token.claimed IS NULL
            ',
            parameters: [
                $clock->now()->format(format: 'Y-m-d H:i:s'),
                $clientData->toJson(),
                $dbAuthToken->id,
            ],
        )->rowCount() === 1;
    }

    /**
     * Deletes the unclaimed tokens of a user (e.g. after a new password), the claimed ones stay in the log.
     */
    public function deleteUnclaimedByUserId(int $userId): void
    {
        $this->db->execute(
            sql: 'DELETE FROM auth_token WHERE user_id=? AND claimed IS NULL',
            parameters: [$userId],
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
