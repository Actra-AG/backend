<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\yuf\mailer\AbstractMailer;

final readonly class MailerSettings
{
    /**
     * @param AbstractMailer $mailer The mailer of the project: `SmtpMailer`, or `GraphMailer` for Microsoft 365 (see
     *                               yuf's docs/mail.md)
     */
    public function __construct(
        public string $senderEmail,
        public string $senderName,
        public string $signature,
        public AbstractMailer $mailer,
    ) {}
}
