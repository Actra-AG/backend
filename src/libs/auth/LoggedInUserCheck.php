<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

use actra\backend\BackendViewContext;
use actra\yuf\auth\UnauthorizedIpAddressException;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use actra\yuf\exception\UnauthorizedException;

/**
 * The checks of a logged-in user on every request, for `BackendView` and `BackendLogin::getCurrentUser()`.
 *
 * @internal
 */
final readonly class LoggedInUserCheck
{
    /**
     * The request must come from the user's own IP whitelist if the user has one. In an impersonation, the
     * impersonating user must still be active and manage users, and the whitelist of the impersonating user applies.
     *
     * @throws UnauthorizedException if the impersonating user may no longer do it (the session is logged out)
     * @throws UnauthorizedIpAddressException if the request comes from an address outside the whitelist
     */
    public static function check(BackendViewContext $context, MyAuthUser $myAuthUser): void
    {
        $whitelistUser = $myAuthUser->dbAuthUser;
        $parentSessionId = $myAuthUser->parentSessionId;
        if ($parentSessionId !== null) {
            $parentSession = $context->repositories->sessions()->selectById(id: $parentSessionId);
            $impersonator = $parentSession === null ? null : new MyAuthUser(
                dbAuthUser: $parentSession->dbAuthUser,
                parentSessionId: null,
                repositories: $context->repositories,
                clientData: $context->clientData,
            );
            if ($impersonator === null || !$impersonator->canManageUsers()) {
                $context->authSession->logOut();
                throw new UnauthorizedException();
            }
            $whitelistUser = $impersonator->dbAuthUser;
        }
        $ipAddress = $context->viewContext->httpRequest->getRemoteAddress();
        if (
            $whitelistUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(whiteList: $whitelistUser->ipWhitelist, ipAddressToCheck: $ipAddress)
        ) {
            throw new UnauthorizedIpAddressException(message: 'Invalid IP address ' . $ipAddress);
        }
    }
}
