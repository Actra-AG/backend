<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

/**
 * The result of `BackendLogin::logInWithCode()`.
 */
enum LoginCodeResultEnum
{
    case LOGGED_IN;
    /**
     * Wrong, expired or used code, a code requested in another browser session, or too many wrong codes in this
     * session (request a new code then)
     */
    case INVALID;
}
