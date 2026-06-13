<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\Password;
use actra\yuf\core\HttpRequest;
use actra\yuf\db\DbQuery;
use actra\yuf\exception\UnauthorizedException;
use stdClass;

class DbAuthApiKeyRepository
{
    private const string API_KEY_PREFIX = 'api_key';
    private const int PUBLIC_ID_BYTES = 6;
    private const int SECRET_BYTES = 32;
    private const string PUBLIC_ID_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public static function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT auth_api_key.userID,
                       auth_api_key.publicID,
                       auth_api_key.apiKey,
                       auth_api_key.salt
                FROM auth_api_key
            '
        );
    }

    private static function createItem(stdClass $data): DbAuthApiKey
    {
        return new DbAuthApiKey(
            userID: $data->userID,
            publicID: $data->publicID,
            key: new Password(
                salt: $data->salt,
                hash: $data->apiKey
            )
        );
    }

    private static function select(DbQuery $dbQuery): DbAuthApiKeyCollection
    {
        $dbAuthApiKeyCollection = new DbAuthApiKeyCollection();
        foreach (
            $dbQuery->selectFromDb(
                db: DB::get(),
                offset: 0,
                rowCount: 1000
            ) as $row
        ) {
            $dbAuthApiKeyCollection->add(
                dbAuthApiKey: DbAuthApiKeyRepository::createItem(data: $row)
            );
        }
        return $dbAuthApiKeyCollection;
    }

    private static function selectByPublicID(string $publicID): ?DbAuthApiKey
    {
        $dbQuery = DbAuthApiKeyRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_api_key.publicID=?',
            parameters: [
                $publicID,
            ]
        );
        $dbAuthApiKeyCollection = DbAuthApiKeyRepository::select(dbQuery: $dbQuery);
        return $dbAuthApiKeyCollection->isEmpty() ? null : $dbAuthApiKeyCollection->getFirst();
    }

    public static function getUserIDForBearerOrThrow(): int
    {
        $bearer = HttpRequest::getBearer();
        if ($bearer === false || $bearer === '') {
            throw new UnauthorizedException();
        }
        $apiKeyParts = DbAuthApiKeyRepository::parseBearer(bearer: $bearer);
        if ($apiKeyParts === null) {
            throw new UnauthorizedException();
        }
        $dbAuthApiKey = DbAuthApiKeyRepository::selectByPublicID(
            publicID: $apiKeyParts['publicID']
        );
        if ($dbAuthApiKey === null) {
            throw new UnauthorizedException();
        }
        if (!$dbAuthApiKey->key->isValid(rawPassword: $apiKeyParts['secret'])) {
            throw new UnauthorizedException();
        }
        return $dbAuthApiKey->userID;
    }

    private static function parseBearer(string $bearer): ?array
    {
        $parts = explode(
            separator: '_',
            string: $bearer
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
            'publicID' => $parts[2],
            'secret' => $parts[3],
        ];
    }

    public static function hasByUserID(int $userID): bool
    {
        $dbQuery = DbAuthApiKeyRepository::getDbQuery();
        $dbQuery->addWherePart(
            wherePart: 'auth_api_key.userID=?',
            parameters: [$userID]
        );
        $dbAuthApiKeyCollection = DbAuthApiKeyRepository::select(dbQuery: $dbQuery);
        return $dbAuthApiKeyCollection->isEmpty() === false;
    }

    private static function createPublicID(): string
    {
        do {
            $publicID = '';
            for ($i = 0; $i < DbAuthApiKeyRepository::PUBLIC_ID_BYTES; $i++) {
                $publicID .= DbAuthApiKeyRepository::PUBLIC_ID_CHARS[random_int(
                    min: 0,
                    max: strlen(string: DbAuthApiKeyRepository::PUBLIC_ID_CHARS) - 1
                )];
            }
        } while (DbAuthApiKeyRepository::selectByPublicID(publicID: $publicID) !== null);

        return $publicID;
    }

    public static function createForUserID(int $userID): string
    {
        $publicID = DbAuthApiKeyRepository::createPublicID();
        $secret = bin2hex(string: random_bytes(length: DbAuthApiKeyRepository::SECRET_BYTES));
        $apiKey = DbAuthApiKeyRepository::API_KEY_PREFIX . '_' . $publicID . '_' . $secret;
        $password = Password::generateNew(
            rawPassword: $secret
        );
        $db = DB::get();
        $db->execute(
            sql: '
                REPLACE INTO auth_api_key
                SET userID=?,
                    publicID=?,
                    apiKey=?,
                    salt=?
            ',
            parameters: [
                $userID,
                $publicID,
                $password->hash,
                $password->salt,
            ]
        );

        return $apiKey;
    }

    public static function deleteByUserID(int $userID): void
    {
        DB::get()->execute(
            sql: '
                DELETE FROM auth_api_key
                WHERE userID=?
            ',
            parameters: [
                $userID,
            ]
        );
    }
}