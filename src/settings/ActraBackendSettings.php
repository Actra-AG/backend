<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\i18n\BackendMessages;
use actra\backend\libs\auth\UserDeleteHandler;
use actra\backend\libs\auth\UserScope;
use actra\yuf\auth\LoginRateLimit;
use InvalidArgumentException;

final readonly class ActraBackendSettings
{
    /**
     * @param list<string> $ipWhitelist
     * @param list<string> $javaScriptPaths
     * @param list<string> $stylesPaths
     * @param string $backendName The name of the backend in the page header and the page title; plain text
     * @param string $frontendHref The link to the website in the page footer: an http(s) URL or a path starting with
     *     `/` (`FooterLink::isAllowedHref()`), `''` for none; its text is `LayoutMessages::$frontendLink` in the
     *     language of the request
     * @param ?LoginRateLimit $loginRateLimit Limits the logins, password verifications and login code requests per
     *     client IP address and email address, and the entered login codes per client IP address (yuf's
     *     `Authenticator`); `null` as explicit opt-out
     * @param ApiKeyIpPolicyEnum $apiKeyIpPolicy `WHITELIST_OPTIONAL`: API keys also work without IP whitelist of their
     *     user, from any address (`docs/users.md`)
     * @param array<string, BackendMessages> $messages Own texts by language code (`ActraBackend::getMessages()`); a
     *     language without entry gets `BackendMessages::forLanguageCode()`
     * @param ?BackendNavigation $projectNavigation Adds the project's navigation items in the language of the request
     * @param ?PageNoticeProvider $pageNoticeProvider Notices above the content of every page after login
     *     (`docs/views.md`)
     * @param ?UserDeleteHandler $userDeleteHandler Deletes the project's data of a user before the user is deleted
     * @param ?LoginHandOverSettings $loginHandOver The login on another host of the same server (login host and
     *     sites, `docs/login.md`); `null` as explicit opt-out: the login stays on the host
     * @param ?SharedLoginSettings $sharedLogin The login of a site on its own login page, shared with the login host
     *     and its other sites (`docs/login.md`); `null` as explicit opt-out
     * @param ?BackendFooterLinks $footerLinks Further links of the page footer (imprint, privacy policy) in the
     *     language of the request, after the link to the website (`docs/views.md`); `null` as explicit opt-out
     * @param ?UserScope $userScope Restricts the built-in user management to the users an actor may manage
     *     (multi-tenant projects, `docs/users.md`); `null` as explicit opt-out: every user with the right to manage
     *     users manages all users
     * @param ?TokenSendLimit $tokenSendLimit Limits the login codes and password reset links per user; `null` turns
     *     the limit off
     * @param bool $passwordLogin `false`: users log in with the login code by email only. The password pages answer
     *     404 (`BackendPaths::PASSWORD_FILE_TITLES`), no page links to them, and stored passwords and the counter of
     *     wrong passwords have no effect (they stay in the database)
     * @throws InvalidArgumentException for a `frontendHref` that `FooterLink::isAllowedHref()` refuses
     */
    public function __construct(
        public array $ipWhitelist,
        public string $backendName,
        public array $javaScriptPaths,
        public array $stylesPaths,
        public ?LoginRateLimit $loginRateLimit,
        public ?LoginHandOverSettings $loginHandOver,
        public ?SharedLoginSettings $sharedLogin,
        public ?BackendFooterLinks $footerLinks,
        public ?UserScope $userScope,
        public int $maxAllowedLoginAttempts = 5,
        public string $frontendHref = '',
        public bool $hasApi = false,
        public ApiKeyIpPolicyEnum $apiKeyIpPolicy = ApiKeyIpPolicyEnum::WHITELIST_REQUIRED,
        public array $messages = [],
        public ?BackendNavigation $projectNavigation = null,
        public ?PageNoticeProvider $pageNoticeProvider = null,
        public ?UserDeleteHandler $userDeleteHandler = null,
        public ?TokenSendLimit $tokenSendLimit = new TokenSendLimit(),
        public bool $passwordLogin = true,
    ) {
        if ($frontendHref !== '' && !FooterLink::isAllowedHref(href: $frontendHref)) {
            throw new InvalidArgumentException(
                message: 'frontendHref must be an http(s) URL or a path starting with "/": ' . $frontendHref,
            );
        }
    }
}
