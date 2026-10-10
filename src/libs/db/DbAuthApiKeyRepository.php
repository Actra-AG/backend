<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\Password;
use actra\yuf\auth\SecretTokenHash;
use actra\yuf\common\StringUtils;
use actra\yuf\db\DbQuery;
use actra\yuf\db\DbRow;

final class DbAuthApiKeyRepository
{
    public function __construct(private readonly DB $db) {}

    private const string API_KEY_PREFIX = 'api_key';
    private const int PUBLIC_ID_LENGTH = 6;
    private const int SECRET_BYTES = 32;
    private const string PUBLIC_ID_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_api_key.user_id,
                       auth_api_key.public_id,
                       auth_api_key.api_key,
                       auth_api_key.salt
                FROM auth_api_key
            ',
        );
    }

    private function createItem(DbRow $row): DbAuthApiKey
    {
        return new DbAuthApiKey(
            userId: $row->getInt(column: 'user_id'),
            publicId: $row->getString(column: 'public_id'),
            key: DbAuthApiKeyRepository::createKeyHash(
                salt: $row->getString(column: 'salt'),
                hash: $row->getString(column: 'api_key'),
            ),
        );
    }

    /**
     * A `SecretTokenHash` for keys since v1.11.0 (empty salt, SHA-256 in hex), a `Password` for older keys (salt and
     * SHA-256).
     */
    public static function createKeyHash(string $salt, string $hash): Password|SecretTokenHash
    {
        $secretTokenHash = $salt === '' ? SecretTokenHash::tryFrom(hash: $hash) : null;

        return $secretTokenHash ?? new Password(salt: $salt, hash: $hash);
    }

    private function select(DbQuery $dbQuery): DbAuthApiKeyCollection
    {
        $dbAuthApiKeyCollection = new DbAuthApiKeyCollection();
        foreach (
            $dbQuery->selectRowsFromDb(
                db: $this->db,
                offset: 0,
                rowCount: 1000,
            ) as $row
        ) {
            $dbAuthApiKeyCollection->add(
                dbAuthApiKey: $this->createItem(row: $row),
            );
        }
        return $dbAuthApiKeyCollection;
    }

    private function selectByPublicId(string $publicId): ?DbAuthApiKey
    {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_api_key.public_id=?',
            parameters: [
                $publicId,
            ],
        );
        $dbAuthApiKeyCollection = $this->select(dbQuery: $dbQuery);
        return $dbAuthApiKeyCollection->isEmpty() ? null : $dbAuthApiKeyCollection->getFirst();
    }

    /**
     * The API key of a bearer token (`api_key_<public-id>_<secret>`) if it exists and the secret matches, `null`
     * otherwise. Only the key: the user checks are in `ActraBackend::authenticateBearerOrThrow()`.
     */
    public function findByBearer(#[\SensitiveParameter] string $bearer): ?DbAuthApiKey
    {
        $apiKeyParts = $this->parseBearer(bearer: $bearer);
        if ($apiKeyParts === null) {
            return null;
        }
        $dbAuthApiKey = $this->selectByPublicId(publicId: $apiKeyParts['publicId']);
        if ($dbAuthApiKey === null || !$dbAuthApiKey->isValid(secret: $apiKeyParts['secret'])) {
            return null;
        }

        return $dbAuthApiKey;
    }

    /**
     * @return array{publicId: string, secret: string}|null
     */
    private function parseBearer(#[\SensitiveParameter] string $bearer): ?array
    {
        $parts = explode(
            separator: '_',
            string: $bearer,
        );

        if (count(value: $parts) !== 4) {
            return null;
        }

        if ($parts[0] . '_' . $parts[1] !== DbAuthApiKeyRepository::API_KEY_PREFIX) {
            return null;
        }

        if ($parts[2] === '' || $parts[3] === '') {
            return null;
        }

        return [
            'publicId' => $parts[2],
            'secret' => $parts[3],
        ];
    }

    public function hasByUserId(int $userId): bool
    {
        $dbQuery = $this->getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_api_key.user_id=?',
            parameters: [$userId],
        );
        $dbAuthApiKeyCollection = $this->select(dbQuery: $dbQuery);
        return $dbAuthApiKeyCollection->isEmpty() === false;
    }

    private function createPublicId(): string
    {
        do {
            $publicId = StringUtils::randomFromAlphabet(
                length: DbAuthApiKeyRepository::PUBLIC_ID_LENGTH,
                alphabet: DbAuthApiKeyRepository::PUBLIC_ID_CHARS,
            );
        } while ($this->selectByPublicId(publicId: $publicId) !== null);

        return $publicId;
    }

    public function createForUserId(int $userId): string
    {
        $publicId = $this->createPublicId();
        $secret = bin2hex(string: random_bytes(length: DbAuthApiKeyRepository::SECRET_BYTES));
        $apiKey = DbAuthApiKeyRepository::API_KEY_PREFIX . '_' . $publicId . '_' . $secret;
        $keyHash = SecretTokenHash::fromSecret(secret: $secret);
        $db = $this->db;
        $db->execute(
            sql: '
                REPLACE INTO auth_api_key
                SET user_id=?,
                    public_id=?,
                    api_key=?,
                    salt=?
            ',
            parameters: [
                $userId,
                $publicId,
                $keyHash->hash,
                '',
            ],
        );

        return $apiKey;
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->execute(
            sql: '
                DELETE FROM auth_api_key
                WHERE user_id=?
            ',
            parameters: [
                $userId,
            ],
        );
    }
}
