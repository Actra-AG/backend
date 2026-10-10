<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\AbstractMail;
use actra\yuf\mailer\AbstractMailer;
use actra\yuf\mailer\FixedServerNameResolver;
use actra\yuf\mailer\MailMimeBody;
use actra\yuf\mailer\MailMimeHeader;
use DateTimeImmutable;
use LogicException;
use Override;

/**
 * Records the sent mails instead of delivering them (yuf's `docs/mail.md`, "Tests"). The login code of the last mail
 * is the secret the tests need: the session and the database keep only its hash.
 */
final class RecordingMailer extends AbstractMailer
{
    /** @var list<AbstractMail> */
    public private(set) array $sentMails = [];

    public function __construct()
    {
        parent::__construct(
            serverAddress: '192.0.2.1',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
            serverNameResolver: new FixedServerNameResolver(),
        );
    }

    #[Override]
    public function headerHasTo(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasBcc(): bool
    {
        return false;
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return 998;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $this->sentMails[] = $abstractMail;
    }

    /**
     * Runs the mails kept for after the response and returns the login code of the last mail (its subject starts with
     * the code).
     */
    public function sendAndGetLoginCode(RecordingResponseSender $responseSender): string
    {
        $responseSender->runAfterResponseCallbacks();
        $lastMail = $this->sentMails[array_key_last(array: $this->sentMails) ?? -1]
            ?? throw new LogicException(message: 'No mail was sent.');
        if (preg_match(pattern: '/^([A-Z0-9]{6})\b/', subject: $lastMail->getSubject(), matches: $matches) !== 1) {
            throw new LogicException(message: 'The last mail contains no login code.');
        }

        return $matches[1];
    }
}
