<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthLoginRepository;
use actra\backend\libs\db\DBAuthTokenRepository;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\backend\libs\email\EmailLoginToken;
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
            maxAllowedWrongPasswordAttempts: ActraBackend::get()->maxAllowedLoginAttempts
        );
    }

    public static function get(): MyAuthenticator
    {
        return MyAuthenticator::$instance === null ? new MyAuthenticator() : MyAuthenticator::$instance;
    }

    public function createAndSendAuthToken(DbAuthUser $dbAuthUser): void
    {
        $authTokenTypeEnum = AuthTokenTypeEnum::LOGIN;
        $_SESSION['auth_token'] = DBAuthTokenRepository::createToken(
            dbAuthUser: $dbAuthUser,
            authTokenTypeEnum: $authTokenTypeEnum
        );
        $_SESSION['failedLoginAttempts'] = 0;
        EmailLoginToken::send(
            dbAuthUser: $dbAuthUser,
            loginCode: $_SESSION['auth_token'],
            authTokenTypeEnum: $authTokenTypeEnum
        );
    }

    public function tokenLogin(string $inputToken): bool
    {
        if ($this->getFailedLoginAttempts() > 5) {
            return false;
        }
        if (
            !array_key_exists(
                key: 'auth_token',
                array: $_SESSION
            )
            || $_SESSION['auth_token'] !== $inputToken
        ) {
            $this->increaseFailedLoginAttempts();
            return false;
        }
        unset($_SESSION['auth_token']);
        $dbAuthToken = DBAuthTokenRepository::getClaimable(
            authTokenType: AuthTokenTypeEnum::LOGIN,
            token: $inputToken
        );
        if ($dbAuthToken === null) {
            return false;
        }
        DBAuthTokenRepository::claim(dbAuthToken: $dbAuthToken);

        return $this->doLogin(
            authMethod: AuthMethod::OTP,
            userName: $dbAuthToken->email,
            passwordToCheck: null
        );
    }

    private function getFailedLoginAttempts(): int
    {
        return array_key_exists(
            key: 'failedLoginAttempts',
            array: $_SESSION
        ) ? $_SESSION['failedLoginAttempts'] : 0;
    }

    private function increaseFailedLoginAttempts(): void
    {
        if (!array_key_exists(key: 'failedLoginAttempts', array: $_SESSION)) {
            $_SESSION['failedLoginAttempts'] = 0;
        }
        $_SESSION['failedLoginAttempts']++;
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

    protected function logAuthResult(
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