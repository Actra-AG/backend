<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\settings\MailerSettings;
use actra\yuf\core\ResponseSender;
use actra\yuf\mailer\TextMail;

/**
 * Sends the emails of the backend with the mailer of the project (`BackendViewContext::$mailer`).
 */
final readonly class Mailer
{
    /**
     * @param ResponseSender $responseSender Sends the mails of `sendTextMailAfterResponse()` after the response
     *                                       (`ViewContext::$responseSender`, `NativeResponseSender` in CLI scripts)
     */
    public function __construct(
        public MailerSettings $mailerSettings,
        private ResponseSender $responseSender,
    ) {}

    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public function sendTextMail(
        string $recipient,
        string $subject,
        string $textBody,
        ?string $replyTo = null,
        array $cc = [],
        array $bcc = [],
    ): void {
        $mailerSettings = $this->mailerSettings;
        $textMail = new TextMail(
            senderEmail: $mailerSettings->senderEmail,
            fromEmail: $mailerSettings->senderEmail,
            fromName: $mailerSettings->senderName,
            toEmail: $recipient,
            toName: $recipient,
            subject: $subject,
            textBody: $textBody,
        );
        if ($replyTo !== null) {
            $textMail->addReplyTo(inputEmail: $replyTo);
        }
        foreach ($cc as $ccEmail) {
            $textMail->addCc(inputEmail: $ccEmail);
        }
        foreach ($bcc as $bccEmail) {
            $textMail->addBcc(inputEmail: $bccEmail);
        }
        $textMail->send(abstractMailer: $mailerSettings->mailer);
    }

    /**
     * Sends the mail after the response, so the response time does not depend on whether a mail is sent (it does not
     * tell whether an email address exists). A failure is logged by the exception handler; the user does not see it.
     */
    public function sendTextMailAfterResponse(string $recipient, string $subject, string $textBody): void
    {
        $this->responseSender->afterResponse(callback: fn() => $this->sendTextMail(
            recipient: $recipient,
            subject: $subject,
            textBody: $textBody,
        ));
    }
}
