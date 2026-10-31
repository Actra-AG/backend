<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\i18n\BackendMessages;
use actra\backend\libs\auth\UserDeleteHandler;
use actra\yuf\auth\LoginRateLimit;

final readonly class ActraBackendSettings
{
    /**
     * @param list<string> $ipWhitelist
     * @param list<string> $javaScriptPaths
     * @param list<string> $stylesPaths
     * @param ?LoginRateLimit $loginRateLimit Limits the logins, password verifications and login code requests per
     *     client IP address and email address (yuf's `Authenticator`); `null` as explicit opt-out
     * @param array<string, BackendMessages> $messages Own texts by language code (`ActraBackend::getMessages()`); a
     *     language without entry gets `BackendMessages::forLanguageCode()`
     * @param ?BackendNavigation $projectNavigation Adds the project's navigation items in the language of the request
     * @param ?UserDeleteHandler $userDeleteHandler Deletes the project's data of a user before the user is deleted
     * @param ?TokenSendLimit $tokenSendLimit Limits the login codes and password reset links per user; `null` turns
     *     the limit off
     */
    public function __construct(
        public array $ipWhitelist,
        public string $backendName,
        public array $javaScriptPaths,
        public array $stylesPaths,
        public ?LoginRateLimit $loginRateLimit,
        public int $maxAllowedLoginAttempts = 5,
        public string $frontendHref = '',
        public string $frontendName = '',
        public bool $hasApi = false,
        public array $messages = [],
        public ?BackendNavigation $projectNavigation = null,
        public ?UserDeleteHandler $userDeleteHandler = null,
        public ?TokenSendLimit $tokenSendLimit = new TokenSendLimit(),
    ) {}
}
