<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use InvalidArgumentException;

/**
 * How many one-time tokens of one type (login code, password reset link) the backend sends to a user within a time
 * window. Above the limit, the forms answer as before but send nothing, so a known address cannot be flooded with mails.
 */
final readonly class TokenSendLimit
{
    /**
     * @param int $maxTokens The most tokens of one type per user within the window
     * @param int $withinMinutes The length of the window
     *
     * @throws InvalidArgumentException if a value is not positive
     */
    public function __construct(
        public int $maxTokens = 5,
        public int $withinMinutes = 15,
    ) {
        if ($maxTokens < 1 || $withinMinutes < 1) {
            throw new InvalidArgumentException(message: 'The token send limit needs positive values.');
        }
    }
}
