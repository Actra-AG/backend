<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\yuf\session\Session;

/**
 * The ID of the shared login (yuf's `SharedLogin`) of the login in the session: set at every login, removed with the
 * data of the user at the logout (`AuthSession::logOut()`).
 *
 * @internal
 */
final readonly class SharedLoginSession
{
    private const string KEY = 'actraBackendSharedLoginId';

    public static function find(Session $session): ?string
    {
        return $session->getString(key: SharedLoginSession::KEY);
    }

    public static function store(Session $session, string $sharedLoginId): void
    {
        $session->set(key: SharedLoginSession::KEY, value: $sharedLoginId);
    }
}
