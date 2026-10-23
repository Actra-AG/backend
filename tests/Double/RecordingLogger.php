<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\core\Logger;
use Throwable;

/**
 * A `Logger` that keeps the logged messages and exceptions for assertions.
 */
final class RecordingLogger implements Logger
{
    /** @var list<string> */
    public private(set) array $messages = [];
    /** @var list<Throwable> */
    public private(set) array $exceptions = [];

    #[\Override]
    public function logException(Throwable $throwable): void
    {
        $this->exceptions[] = $throwable;
    }

    #[\Override]
    public function logMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}
