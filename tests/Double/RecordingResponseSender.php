<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\ResponseSender;
use Closure;
use LogicException;
use Override;

/**
 * Keeps the callbacks for after the response instead of running them at the end of the test run; a sent response
 * throws (views are not run in the unit tests).
 */
final class RecordingResponseSender implements ResponseSender
{
    /** @var list<Closure(): void> */
    public private(set) array $afterResponseCallbacks = [];

    #[Override]
    public function send(HttpResponse $httpResponse): never
    {
        throw new LogicException(message: 'The unit tests send no response.');
    }

    #[Override]
    public function afterResponse(Closure $callback): void
    {
        $this->afterResponseCallbacks[] = $callback;
    }
}
