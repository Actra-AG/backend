<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\yuf\session\Session;

/**
 * Keeps a newly generated API key in the session until the page after the POST redirect has shown it once. The key
 * is never stored in plain text anywhere else, so it must not be put into the redirect URL.
 *
 * @internal
 */
final class GeneratedApiKeyFlash
{
    private const string SESSION_KEY = 'actra_backend_generated_api_key';

    public static function store(Session $session, int $userId, string $apiKey): void
    {
        $session->set(key: GeneratedApiKeyFlash::SESSION_KEY, value: ['userId' => $userId, 'apiKey' => $apiKey]);
    }

    /**
     * Returns the stored key of the user and removes it, so it is shown only once.
     */
    public static function pull(Session $session, int $userId): ?string
    {
        $stored = $session->getArray(key: GeneratedApiKeyFlash::SESSION_KEY);
        if (
            $stored === null
            || !array_key_exists(key: 'userId', array: $stored)
            || !array_key_exists(key: 'apiKey', array: $stored)
            || $stored['userId'] !== $userId
            || !is_string(value: $stored['apiKey'])
        ) {
            return null;
        }
        $session->remove(key: GeneratedApiKeyFlash::SESSION_KEY);

        return $stored['apiKey'];
    }
}
