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
use Override;

/**
 * Keeps the callbacks for after the response instead of running them at the end of the test run. A sent response is
 * kept in `$sentResponse` and ends the view with a `ResponseSentException` (instead of `exit`).
 */
final class RecordingResponseSender implements ResponseSender
{
    /** @var list<Closure(): void> */
    public private(set) array $afterResponseCallbacks = [];

    public private(set) ?HttpResponse $sentResponse = null;

    #[Override]
    public function send(HttpResponse $httpResponse): never
    {
        $this->sentResponse = $httpResponse;

        throw new ResponseSentException();
    }

    #[Override]
    public function afterResponse(Closure $callback): void
    {
        $this->afterResponseCallbacks[] = $callback;
    }
}
