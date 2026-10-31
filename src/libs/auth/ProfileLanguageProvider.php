<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\libs\db\BackendRepositories;
use actra\yuf\auth\AuthSession;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\session\Session;

/**
 * The language of a request is the profile language of the logged-in user (`auth_users.language`); during an
 * impersonation that of the impersonating user, who uses the backend. `null` (no login, no profile language): yuf's
 * `RequestLanguageResolver` takes the session, the browser, the first language. Get it with
 * `ActraBackend::createRequestLanguageProvider()`.
 */
final readonly class ProfileLanguageProvider implements RequestLanguageProvider
{
    public function __construct(private BackendRepositories $repositories) {}

    #[\Override]
    public function findLanguageCode(HttpRequest $httpRequest, ?Session $session): ?string
    {
        // A visitor without session cookie is not logged in: no session is started for the question
        if ($session === null || !$session->isActive()) {
            return null;
        }
        $authSession = new AuthSession(session: $session);
        if (!$authSession->isLoggedIn()) {
            return null;
        }

        return $this->repositories->sessions()->findLanguageCode(id: $authSession->getAuthSessionId());
    }
}
