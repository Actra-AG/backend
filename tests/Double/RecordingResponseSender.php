<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\ResponseSender;
use actra\yuf\mailer\RecordingMailer;
use Closure;
use LogicException;
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

    /**
     * Runs and forgets the callbacks kept for after the response (e.g. the mails).
     */
    public function runAfterResponseCallbacks(): void
    {
        $callbacks = $this->afterResponseCallbacks;
        $this->afterResponseCallbacks = [];
        foreach ($callbacks as $callback) {
            $callback();
        }
    }

    /**
     * Runs the callbacks kept for after the response (they send the mails) and returns the login code of the last mail
     * (its subject starts with the code). The code is the secret the tests need: the session and the database keep only
     * its hash.
     */
    public function sendMailsAndGetLoginCode(RecordingMailer $mailer): string
    {
        $this->runAfterResponseCallbacks();
        $lastMail = $mailer->sentMails[array_key_last(array: $mailer->sentMails) ?? -1]
            ?? throw new LogicException(message: 'No mail was sent.');
        if (preg_match(pattern: '/^([A-Z0-9]{6})\b/', subject: $lastMail->getSubject(), matches: $matches) !== 1) {
            throw new LogicException(message: 'The last mail contains no login code.');
        }

        return $matches[1];
    }
}
