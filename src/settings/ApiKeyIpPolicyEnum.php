<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

/**
 * Whether API keys need an IP whitelist of their user (`ActraBackendSettings::$apiKeyIpPolicy`).
 */
enum ApiKeyIpPolicyEnum
{
    /** A key is generated and accepted only for a user with an IP whitelist (default) */
    case WHITELIST_REQUIRED;
    /** A key also works without IP whitelist, from any address; a whitelist of the user is still enforced */
    case WHITELIST_OPTIONAL;

    /**
     * Whether a user with this IP whitelist may have and use an API key.
     *
     * @param list<string> $ipWhitelist
     */
    public function allowsApiKey(array $ipWhitelist): bool
    {
        return $this === ApiKeyIpPolicyEnum::WHITELIST_OPTIONAL || $ipWhitelist !== [];
    }
}
