<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\auth\LoginHandOver;

/**
 * The login on another host of the same server (yuf's `LoginHandOver`, `docs/login.md`): the directory shared by the
 * login host and the sites, and the sites this backend is the login host for. The user IDs are the subject of the
 * hand-over, so all hosts share the user database.
 */
final readonly class LoginHandOverSettings
{
    /**
     * @param LoginHandOver $loginHandOver The hand-over through the directory shared by the login host and the sites;
     *     the same instance as in `LoginTarget::remote()` of a site (`ActraBackend::createRemoteLoginTarget()`)
     * @param list<string> $allowedHosts Login host: the lowercase host names of the sites it logs in
     *     (`site-a.example.com`); `[]` for a site that is no login host
     */
    public function __construct(
        public LoginHandOver $loginHandOver,
        public array $allowedHosts,
    ) {}
}
