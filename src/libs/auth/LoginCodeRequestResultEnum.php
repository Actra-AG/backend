<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\auth;

/**
 * The result of `BackendLogin::requestCode()`.
 */
enum LoginCodeRequestResultEnum
{
    /** Answered for every address that is not blocked by the rate limit, also for unknown or rejected ones */
    case SENT;
    /** Too many requests for the address or from the client: nothing was sent */
    case RATE_LIMITED;
}
