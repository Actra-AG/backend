<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use RuntimeException;

/**
 * Thrown by `RecordingResponseSender::send()` where the real sender ends the process.
 */
final class ResponseSentException extends RuntimeException {}
