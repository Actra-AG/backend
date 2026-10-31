<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\libs\db\BackendRepositories;
use actra\yuf\core\Logger;
use actra\yuf\mailer\SentMail;
use actra\yuf\mailer\SentMailListener;
use RuntimeException;

/**
 * Writes every mail of the mailer of the `MailerSettings` to the log of sent emails (page "Sent emails"), attached by
 * `ActraBackend::create()`. The mail is sent at that point: a database error is logged instead of thrown, so a
 * caller never sends the mail a second time.
 *
 * @internal
 */
final readonly class SentEmailLogger implements SentMailListener
{
    /**
     * @param ?Logger $logger Logs a failed entry; `null`: PHP's `error_log()`
     */
    public function __construct(
        private BackendRepositories $repositories,
        private ?Logger $logger,
    ) {}

    #[\Override]
    public function onMailSent(SentMail $sentMail): void
    {
        try {
            // Opens the database connection of the backend on the first mail
            $this->repositories->sentEmails()->insert(sentMail: $sentMail);
        } catch (RuntimeException $runtimeException) {
            // PDOException (connection) and DbRuntimeException (query) are RuntimeExceptions
            if ($this->logger === null) {
                error_log(message: 'Sent email not logged: ' . $runtimeException->getMessage());

                return;
            }
            $this->logger->logException(throwable: $runtimeException);
        }
    }
}
