<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthLoginRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\settings\AuthTokenTypeEnum;
use actra\yuf\auth\Authenticator;
use actra\yuf\auth\AuthMethod;
use actra\yuf\auth\AuthResult;
use actra\yuf\auth\AuthUser;

class MyAuthenticator extends Authenticator
{
    private static ?MyAuthenticator $instance = null;
    private(set) MyAuthUser $user;

    private function __construct()
    {
        MyAuthenticator::$instance = $this;
        parent::__construct(
            maxAllowedWrongPasswordAttempts: ActraBackend::get()->actraBackendSettings->maxAllowedLoginAttempts
        );
    }

    public static function get(): MyAuthenticator
    {
        return MyAuthenticator::$instance === null ? new MyAuthenticator() : MyAuthenticator::$instance;
    }

    public function tokenLogin(string $inputToken): bool
    {
        $dbAuthToken = AuthTokenTypeEnum::LOGIN->claim(inputToken: $inputToken);
        if ($dbAuthToken === null) {
            return false;
        }
        return $this->doLogin(
            authMethod: AuthMethod::OTP,
            userName: $dbAuthToken->email,
            passwordToCheck: null
        );
    }

    protected function checkLoginCredentials(AuthUser $authUser): bool
    {
        return true;
    }

    protected function createAuthUserByUserName(string $userName): ?MyAuthUser
    {
        $dbAuthUser = DbAuthUserRepository::selectByEmail(email: $userName);
        if ($dbAuthUser === null) {
            return null;
        }
        $this->user = MyAuthUser::createFromDbAuthUser(dbAuthUser: $dbAuthUser);
        return $this->user;
    }

    public function logAuthResult(
        ?int $userID,
        string $sessionID,
        string $ip,
        string $userName,
        AuthResult $authResult
    ): void {
        DbAuthLoginRepository::insert(
            userID: $userID,
            sessionID: $sessionID,
            ipAddress: $ip,
            inputEmail: $userName,
            authResult: $authResult
        );
    }
}