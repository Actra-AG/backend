<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\Password;
use actra\yuf\auth\SecretTokenHash;

/**
 * An API key of a user. Keys since v1.11.0 are stored as `SecretTokenHash` (SHA-256 of the random secret, checked on
 * every API request); older keys keep their `Password` hash until they are generated again.
 */
final readonly class DbAuthApiKey
{
    public function __construct(
        public int $userId,
        public string $publicId,
        public Password|SecretTokenHash $key,
    ) {}

    public function isValid(string $secret): bool
    {
        return $this->key instanceof SecretTokenHash
            ? $this->key->isValid(secret: $secret)
            : $this->key->isValid(rawPassword: $secret);
    }
}
