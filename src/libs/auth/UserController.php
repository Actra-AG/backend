<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\libs\db\DB;
use actra\backend\libs\db\DbAuthApiKeyRepository;
use actra\backend\libs\db\DbAuthIpWhitelistRepository;
use actra\backend\libs\db\DbAuthLoginRepository;
use actra\backend\libs\db\DbAuthSessionRepository;
use actra\backend\libs\db\DbAuthTokenRepository;
use actra\backend\libs\db\DbAuthUserGroupRepository;
use actra\backend\libs\db\DbAuthUserRepository;
use actra\yuf\auth\AuthSession;
use Throwable;

class UserController
{
    private static ?UserDeleteHandlerInterface $userDeleteHandler = null;

    public static function registerUserDeleteHandler(UserDeleteHandlerInterface $userDeleteHandler): void
    {
        UserController::$userDeleteHandler = $userDeleteHandler;
    }

    public static function deleteUser(int $userID): void
    {
        $db = DB::get();
        $db->beginTransaction();
        try {
            DbAuthLoginRepository::unsetUserID(userID: $userID);
            DbAuthSessionRepository::deleteByUserID(userID: $userID);
            DbAuthTokenRepository::deleteByUserID(userID: $userID);
            DbAuthUserGroupRepository::deleteByUserID(userID: $userID);
            DbAuthIpWhitelistRepository::deleteByUserID(userID: $userID);
            DbAuthApiKeyRepository::deleteByUserID(userID: $userID);
            UserController::$userDeleteHandler?->beforeDeleteUser($userID);
            DbAuthUserRepository::delete(ID: $userID);
            $db->commit();
        } catch (Throwable $throwable) {
            $db->rollBack();
            throw $throwable;
        }
        if (MyAuthUser::get()->ID === $userID) {
            AuthSession::logOut();
        }
    }
}