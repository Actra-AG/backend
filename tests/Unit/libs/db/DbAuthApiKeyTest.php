<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\db;

use actra\backend\libs\db\DbAuthApiKey;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\yuf\auth\Password;
use actra\yuf\auth\SecretTokenHash;
use PHPUnit\Framework\TestCase;

final class DbAuthApiKeyTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private function createApiKey(string $salt, string $hash): DbAuthApiKey
    {
        return new DbAuthApiKey(
            userID: 5,
            publicID: 'ABC123',
            key: DbAuthApiKeyRepository::createKeyHash(salt: $salt, hash: $hash),
        );
    }

    public function testNewKeyIsASecretTokenHash(): void
    {
        $hash = SecretTokenHash::fromSecret(secret: DbAuthApiKeyTest::SECRET)->hash;

        $apiKey = $this->createApiKey(salt: '', hash: $hash);

        $this->assertInstanceOf(SecretTokenHash::class, $apiKey->key);
        $this->assertTrue($apiKey->isValid(secret: DbAuthApiKeyTest::SECRET));
        $this->assertFalse($apiKey->isValid(secret: 'wrong'));
    }

    public function testKeyWithSaltIsCheckedWithTheLegacyHash(): void
    {
        $salt = 'abcdefghijklmnop';
        $apiKey = $this->createApiKey(salt: $salt, hash: hash(algo: 'sha256', data: $salt . DbAuthApiKeyTest::SECRET));

        $this->assertInstanceOf(Password::class, $apiKey->key);
        $this->assertTrue($apiKey->isValid(secret: DbAuthApiKeyTest::SECRET));
        $this->assertFalse($apiKey->isValid(secret: 'wrong'));
    }

    public function testArgon2idKeyWithoutSaltStaysAPassword(): void
    {
        $password = Password::generateNew(rawPassword: DbAuthApiKeyTest::SECRET);

        $apiKey = $this->createApiKey(salt: $password->salt, hash: $password->hash);

        $this->assertInstanceOf(Password::class, $apiKey->key);
        $this->assertTrue($apiKey->isValid(secret: DbAuthApiKeyTest::SECRET));
    }
}
