<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\BackendView;
use actra\backend\libs\db\DbAuthSessionRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\yuf\auth\AuthUser;
use actra\yuf\auth\Password;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\UnauthorizedException;

final class MyAuthUser extends AuthUser
{
    private static ?MyAuthUser $instance = null;

    private function __construct(
        public readonly DbAuthUser $dbAuthUser,
        public readonly ?int $parentSessionID,
    ) {
        MyAuthUser::$instance = $this;
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

    public static function createFromDbAuthUser(DbAuthUser $dbAuthUser): MyAuthUser
    {
        return new MyAuthUser(
            dbAuthUser: $dbAuthUser,
            parentSessionID: null,
        );
    }

    public static function setRequestedPageAfterLogin(string $path): void
    {
        ActraBackend::get()->getSession()->set(key: 'requestedPageAfterLogin', value: $path);
    }

    public function redirectToFirstAllowedPage(): void
    {
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $this->getFirstAllowedPage(),
            httpRequest: ActraBackend::get()->getViewContext()->httpRequest,
        );
    }

    public function getFirstAllowedPage(): string
    {
        $session = ActraBackend::get()->getSession();
        $requestedPage = $session->getString(key: 'requestedPageAfterLogin');
        $session->remove(key: 'requestedPageAfterLogin');
        $target = is_string(value: $requestedPage) && $requestedPage !== ''
            ? $requestedPage
            : $this->getFirstNavigationHref();
        $target = $this->moveToLanguageRoute(target: $target);

        return $target . (str_contains(haystack: $target, needle: '?') ? '&' : '?') . BackendView::PARAM_FROM_LOGIN;
    }

    private function getFirstNavigationHref(): string
    {
        $navigationItem = ActraBackend::get()->navigationItemCollection->getFirst(
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
    private function moveToLanguageRoute(string $target): string
    {
        $languageCode = $this->dbAuthUser->languageCode;
        if ($languageCode === null) {
            return $target;
        }
        $backendRouteCollection = ActraBackend::get()->backendRouteCollection;
        $languageRoute = $backendRouteCollection->findByLanguage(languageCode: $languageCode);
        if ($languageRoute === null) {
            return $target;
        }

        return $backendRouteCollection->translatePath(uri: $target, targetRoute: $languageRoute);
    }

    public static function get(): MyAuthUser
    {
        if (MyAuthUser::$instance !== null) {
            return MyAuthUser::$instance;
        }
        $authSessionId = ActraBackend::get()->getAuthSession()->getAuthSessionId();
        $dbAuthSession = DbAuthSessionRepository::selectByID(ID: $authSessionId);
        if ($dbAuthSession === null) {
            throw new UnauthorizedException();
        }
        return new MyAuthUser(
            dbAuthUser: $dbAuthSession->dbAuthUser,
            parentSessionID: $dbAuthSession->parentID,
        );
    }

    public function getUserName(): string
    {
        return $this->dbAuthUser->renderFullName();
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
        DbAuthUserRepository::increaseWrongPasswordAttempts(ID: $this->id);
    }

    #[\Override]
    protected function dbConfirmSuccessfulLogin(): int
    {
        DbAuthUserRepository::dbConfirmSuccessfulLogin(ID: $this->id);

        return DbAuthSessionRepository::insert(
            parentID: $this->parentSessionID,
            userID: $this->id,
        );
    }

    #[\Override]
    protected function dbUpdatePassword(Password $newPassword): void
    {
        DbAuthUserRepository::updatePasswordHash(ID: $this->id, password: $newPassword);
    }

    public function canManageUsers(): bool
    {
        return $this->dbAuthUser->accessRightCollection->hasAccessRight(
            accessRight: ActraBackend::RIGHT_MANAGE_USERS,
        );
    }
}
