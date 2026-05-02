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
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\AuthUser;
use actra\yuf\auth\Password;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\UnauthorizedException;

class MyAuthUser extends AuthUser
{
    private static ?MyAuthUser $instance = null;

    private function __construct(
        public readonly DbAuthUser $dbAuthUser,
        public readonly ?int $parentSessionID
    ) {
        MyAuthUser::$instance = $this;
        parent::__construct(
            ID: $dbAuthUser->ID,
            isActive: (
                $dbAuthUser->isActive
                && !$dbAuthUser->accessRightCollection->isEmpty()
            ),
            wrongPasswordAttempts: 0,
            accessRightCollection: $dbAuthUser->accessRightCollection,
            password: Password::generateNew(rawPassword: 'unused')
        );
    }

    public static function createFromDbAuthUser(DbAuthUser $dbAuthUser): MyAuthUser
    {
        return new MyAuthUser(
            dbAuthUser: $dbAuthUser,
            parentSessionID: null
        );
    }

    public static function setRequestedPageAfterLogin(string $path): void
    {
        $_SESSION['requestedPageAfterLogin'] = $path;
    }

    public function redirectToFirstAllowedPage(): void
    {
        HttpResponse::redirectAndExit(relativeOrAbsoluteUri: $this->getFirstAllowedPage());
    }

    public function getFirstAllowedPage(): string
    {
        if (array_key_exists(
            key: 'requestedPageAfterLogin',
            array: $_SESSION
        )) {
            $target = $_SESSION['requestedPageAfterLogin'];
            unset($_SESSION['requestedPageAfterLogin']);
        } else {
            $target = ActraBackend::get()->navigationItemCollection->getFirst(
                authUser: $this
            )->href;
        }
        return $target . (str_contains(
                haystack: $target,
                needle: '?'
            ) ? '&' : '?') . BackendView::PARAM_FROM_LOGIN;
    }

    public static function get(): MyAuthUser
    {
        if (MyAuthUser::$instance !== null) {
            return MyAuthUser::$instance;
        }
        $dbAuthSession = DbAuthSessionRepository::selectByID(ID: AuthSession::getAuthSessionID());
        if ($dbAuthSession === null) {
            throw new UnauthorizedException();
        }
        return new MyAuthUser(
            dbAuthUser: $dbAuthSession->dbAuthUser,
            parentSessionID: $dbAuthSession->parentID
        );
    }

    public function getUserName(): string
    {
        return $this->dbAuthUser->firstName . ' ' . $this->dbAuthUser->lastName;
    }

    public function canImpersonateUser(DbAuthUser $dbAuthUser): bool
    {
        if ($this->isSessionChange()) {
            return false;
        }
        if ($dbAuthUser->ID === $this->ID) {
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

    protected function dbIncreaseWrongPasswordAttempts(): void
    {
    }

    protected function dbConfirmSuccessfulLogin(): int
    {
        DbAuthUserRepository::dbConfirmSuccessfulLogin(ID: $this->ID);

        return DbAuthSessionRepository::insert(
            parentID: $this->parentSessionID,
            userID: $this->ID
        );
    }

    public function canManageUsers(): bool
    {
        return (
            $this->dbAuthUser->accessRightCollection->hasAccessRight(
                accessRight: ActraBackend::RIGHT_MANAGE_USERS
            )
            || $this->isSessionChange()
        );
    }
}