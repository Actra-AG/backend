<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\yuf\session\Session;

/**
 * The URL on another host that a login continues to after a form (push of the shared login, one-way hand-over). The
 * response to a form never redirects to another host: browsers apply the CSP directive `form-action 'self'` to all
 * redirects that follow a form, also to later redirects of the chain. The form redirects to the hand-over page of its
 * own host with an ID (`PARAMETER`); that page shows a short page that continues with a refresh, a new navigation.
 *
 * @internal
 */
final readonly class LoginContinuation
{
    public const string PARAMETER = 'loginContinue';
    private const string KEY = 'actraBackendLoginContinuation';

    /**
     * Stores the URL in the session (replaces one stored before); the ID for the hand-over page.
     */
    public static function store(Session $session, string $url): string
    {
        $id = bin2hex(string: random_bytes(length: 16));
        $session->set(key: LoginContinuation::KEY, value: ['id' => $id, 'url' => $url]);

        return $id;
    }

    /**
     * The URL stored with the ID, once; `null` for another or no ID (the stored URL is removed either way).
     */
    public static function take(Session $session, ?string $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $stored = $session->getStringMap(key: LoginContinuation::KEY);
        if ($stored === null) {
            return null;
        }
        $session->remove(key: LoginContinuation::KEY);

        return hash_equals(known_string: $stored['id'] ?? '', user_string: $id) ? $stored['url'] ?? null : null;
    }
}
