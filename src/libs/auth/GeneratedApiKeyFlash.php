<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

/**
 * Keeps a newly generated API key in the session until the page after the POST redirect has shown it once. The key
 * is never stored in plain text anywhere else, so it must not be put into the redirect URL.
 */
final class GeneratedApiKeyFlash
{
    private const string SESSION_KEY = 'actra_backend_generated_api_key';

    public static function store(int $userID, string $apiKey): void
    {
        $_SESSION[GeneratedApiKeyFlash::SESSION_KEY] = [
            'userID' => $userID,
            'apiKey' => $apiKey,
        ];
    }

    /**
     * Returns the stored key of the user and removes it, so it is shown only once.
     */
    public static function pull(int $userID): ?string
    {
        $stored = $_SESSION[GeneratedApiKeyFlash::SESSION_KEY] ?? null;
        if (
            !is_array(value: $stored)
            || ($stored['userID'] ?? null) !== $userID
            || !is_string(value: $stored['apiKey'] ?? null)
        ) {
            return null;
        }
        unset($_SESSION[GeneratedApiKeyFlash::SESSION_KEY]);

        return $stored['apiKey'];
    }
}