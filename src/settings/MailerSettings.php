<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\settings;

use actra\backend\i18n\EmailMessages;
use actra\backend\i18n\MessageTemplate;
use actra\yuf\mailer\AbstractMailer;

final readonly class MailerSettings
{
    /**
     * @param string $senderName Also the default signature of the emails (`EmailMessages::$signature`)
     * @param AbstractMailer $mailer The mailer of the project: `SmtpMailer`, or `GraphMailer` for Microsoft 365 (see
     *                               yuf's docs/mail.md)
     */
    public function __construct(
        public string $senderEmail,
        public string $senderName,
        public AbstractMailer $mailer,
    ) {}

    /**
     * The signature below the closing greeting, in the language of the email (`EmailMessages::$signature`).
     */
    public function createSignature(EmailMessages $emailMessages): string
    {
        return MessageTemplate::fill(template: $emailMessages->signature, values: ['senderName' => $this->senderName]);
    }
}
