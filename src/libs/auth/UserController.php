<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\db\DbAuthIpWhitelistRepository;
use actra\backend\libs\db\DbAuthLoginRepository;
use actra\backend\libs\db\DbAuthSessionRepository;
use actra\backend\libs\db\DbAuthTokenRepository;
use actra\backend\libs\db\DbAuthUserGroupRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\yuf\auth\AuthSession;

class UserController
{
    public static function deleteUser(int $userID): void
    {
        DbAuthLoginRepository::unsetUserID(userID: $userID);
        DbAuthSessionRepository::deleteByUserID(userID: $userID);
        DbAuthTokenRepository::deleteByUserID(userID: $userID);
        DbAuthUserGroupRepository::deleteByUserID(userID: $userID);
        DbAuthIpWhitelistRepository::deleteByUserID(userID: $userID);
        DbAuthApiKeyRepository::deleteByUserID(userID: $userID);
        DbAuthUserRepository::delete(ID: $userID);
        if (MyAuthUser::get()->ID === $userID) {
            AuthSession::logOut();
        }
    }
}