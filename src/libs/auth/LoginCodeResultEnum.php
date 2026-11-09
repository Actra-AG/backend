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
    /**
     * Too many code attempts from the client IP address (`codeLimits` of the `LoginRateLimit`); the code was not
     * checked
     */
    case RATE_LIMITED;
}
