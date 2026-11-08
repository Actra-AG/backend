<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\auth\SharedLogin;
use actra\yuf\auth\SharedLoginSite;
use InvalidArgumentException;

/**
 * The shared login of several hosts of the same server (yuf's `SharedLogin`, `docs/login.md`): the visitor logs in on
 * the login page of the site, the login is passed to the login host and back by invisible redirects over the
 * hand-over page of the backend (`loginShared`). The user IDs are the subject, so all hosts share the user database.
 * Either the login host (`allowedHosts`) or a site (`site`).
 */
final readonly class SharedLoginSettings
{
    /**
     * @param SharedLogin $sharedLogin The directory shared by the login host and the sites, outside the document roots
     * @param list<string> $allowedHosts Login host: the lowercase host names of its sites; `[]` for a site
     * @param ?SharedLoginSite $site Site: its login page, its hand-over page (`BackendPaths::loginShared()`) and the
     *     hand-over page of the login host; `null` for the login host
     *
     * @throws InvalidArgumentException if both or none of `allowedHosts` and `site` are given
     */
    public function __construct(
        public SharedLogin $sharedLogin,
        public array $allowedHosts,
        public ?SharedLoginSite $site,
    ) {
        if (($allowedHosts === []) === ($site === null)) {
            throw new InvalidArgumentException(
                message: 'A shared login is either the login host (allowedHosts) or a site (site), not both or none.',
            );
        }
    }

    public function isLoginHost(): bool
    {
        return $this->site === null;
    }
}
