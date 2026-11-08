<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

/**
 * The result of `BackendLogin::requestCodeWithPassword()`.
 */
enum PasswordLoginResultEnum
{
    /** The password is correct: the login code is sent (unless above the `TokenSendLimit`), enter it next */
    case CODE_SENT;
    /** Unknown address, wrong password or rejected user (inactive, IP whitelist, out tried); not told apart */
    case CREDENTIALS_INVALID;
    /** Too many attempts for the address or from the client: the password was not checked */
    case RATE_LIMITED;
}
