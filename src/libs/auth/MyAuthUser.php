<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\BackendViewContext;
use actra\backend\i18n\CommonMessages;
use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\ClientData;
use actra\backend\libs\db\DbAuthUser;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\AuthUser;
use actra\yuf\auth\Password;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\session\Session;

final class MyAuthUser extends AuthUser
{
    public function __construct(
        public readonly DbAuthUser $dbAuthUser,
        public readonly ?int $parentSessionID,
        private readonly BackendRepositories $repositories,
        private readonly ClientData $clientData,
    ) {
        parent::__construct(
            id: $dbAuthUser->ID,
            isActive: (
                $dbAuthUser->isActive
                && !$dbAuthUser->accessRightCollection->isEmpty()
            ),
            wrongPasswordAttempts: $dbAuthUser->wrongLoginAttempts,
            accessRightCollection: $dbAuthUser->accessRightCollection,
            password: $dbAuthUser->password ?? MyAuthUser::createPasswordOfUserWithoutPassword(),
            ipWhitelist: $dbAuthUser->ipWhitelist,
        );
    }

    /**
     * A user without password cannot log in with a password: no input matches this hash (not a valid hash format), and
     * no Argon2id hash has to be computed for every loaded user.
     */
    private static function createPasswordOfUserWithoutPassword(): Password
    {
        return new Password(salt: '', hash: '!');
    }

    /**
     * The logged-in user of the session, `null` without login. A login whose session no longer exists in the database
     * (e.g. the user was deleted) is logged out.
     */
    public static function findLoggedIn(
        AuthSession $authSession,
        BackendRepositories $repositories,
        ClientData $clientData,
    ): ?MyAuthUser {
        if (!$authSession->isLoggedIn()) {
            return null;
        }
        $dbAuthSession = $repositories->sessions()->selectByID(ID: $authSession->getAuthSessionId());
        if ($dbAuthSession === null) {
            $authSession->logOut();

            return null;
        }

        return new MyAuthUser(
            dbAuthUser: $dbAuthSession->dbAuthUser,
            parentSessionID: $dbAuthSession->parentID,
            repositories: $repositories,
            clientData: $clientData,
        );
    }

    public static function setRequestedPageAfterLogin(Session $session, string $path): void
    {
        $session->set(key: 'requestedPageAfterLogin', value: $path);
    }

    public function redirectToFirstAllowedPage(BackendViewContext $context): never
    {
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $this->getFirstAllowedPage(context: $context),
            httpRequest: $context->viewContext->httpRequest,
        );
    }

    public function getFirstAllowedPage(BackendViewContext $context): string
    {
        $session = $context->session;
        $requestedPage = $session->getString(key: 'requestedPageAfterLogin');
        $session->remove(key: 'requestedPageAfterLogin');
        $target = is_string(value: $requestedPage) && $requestedPage !== ''
            ? $requestedPage
            : $this->getFirstNavigationHref(context: $context);
        $target = $this->moveToLanguageRoute(context: $context, target: $target);

        return $target . (str_contains(haystack: $target, needle: '?') ? '&' : '?') . BackendView::PARAM_FROM_LOGIN;
    }

    private function getFirstNavigationHref(BackendViewContext $context): string
    {
        $navigationItem = $context->actraBackend->navigationItemCollection->getFirst(
            accessRightCollection: $this->dbAuthUser->accessRightCollection,
        );
        if ($navigationItem === null) {
            throw new UnauthorizedException();
        }

        return $navigationItem->href;
    }

    /**
     * A user with a language continues on the backend route of that language. Without a language (no preference) or
     * for a language without route, the user stays on the route of the login.
     */
    private function moveToLanguageRoute(BackendViewContext $context, string $target): string
    {
        $languageCode = $this->dbAuthUser->languageCode;
        if ($languageCode === null) {
            return $target;
        }
        $backendRouteCollection = $context->actraBackend->backendRouteCollection;
        $languageRoute = $backendRouteCollection->findByLanguage(languageCode: $languageCode);
        if ($languageRoute === null) {
            return $target;
        }

        return $backendRouteCollection->translatePath(uri: $target, targetRoute: $languageRoute);
    }

    public function getUserName(CommonMessages $messages): string
    {
        return $this->dbAuthUser->renderFullName(messages: $messages);
    }

    public function canImpersonateUser(DbAuthUser $dbAuthUser): bool
    {
        if ($this->isSessionChange()) {
            return false;
        }
        if ($dbAuthUser->ID === $this->id) {
            return false;
        }
        if (!$dbAuthUser->isActive) {
            return false;
        }
        if ($dbAuthUser->accessRightCollection->isEmpty()) {
            return false;
        }

        return true;
    }

    public function isSessionChange(): bool
    {
        return $this->parentSessionID !== null;
    }

    #[\Override]
    protected function dbIncreaseWrongPasswordAttempts(): void
    {
        $this->repositories->users()->increaseWrongPasswordAttempts(ID: $this->id);
    }

    #[\Override]
    protected function dbConfirmSuccessfulLogin(): int
    {
        $this->repositories->users()->dbConfirmSuccessfulLogin(ID: $this->id);

        return $this->repositories->sessions()->insert(
            parentID: $this->parentSessionID,
            userID: $this->id,
            clientData: $this->clientData,
        );
    }

    #[\Override]
    protected function dbUpdatePassword(Password $newPassword): void
    {
        $this->repositories->users()->updatePasswordHash(ID: $this->id, password: $newPassword);
    }

    public function canManageUsers(): bool
    {
        return $this->dbAuthUser->accessRightCollection->hasAccessRight(
            accessRight: ActraBackend::RIGHT_MANAGE_USERS,
        );
    }
}
