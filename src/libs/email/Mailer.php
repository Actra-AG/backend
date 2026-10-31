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
     * @param SentEmailSecrets $sentEmailSecrets Shared with the log of sent emails (`ActraBackend::createMailer()`)
     */
    public function __construct(
        public MailerSettings $mailerSettings,
        private ResponseSender $responseSender,
        private SentEmailSecrets $sentEmailSecrets = new SentEmailSecrets(),
    ) {}

    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $secrets Parts of the subject the log of sent emails must not show (e.g. a login code);
     *                              the mail itself contains them
     */
    public function sendTextMail(
        string $recipient,
        string $subject,
        string $textBody,
        ?string $replyTo = null,
        array $cc = [],
        array $bcc = [],
        #[\SensitiveParameter]
        array $secrets = [],
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
        $this->sentEmailSecrets->maskWhile(
            secrets: $secrets,
            send: static fn() => $textMail->send(abstractMailer: $mailerSettings->mailer),
        );
    }

    /**
     * Sends the mail after the response, so the response time does not depend on whether a mail is sent (it does not
     * tell whether an email address exists). A failure is logged by the exception handler; the user does not see it.
     *
     * @param list<string> $secrets See `sendTextMail()`
     */
    public function sendTextMailAfterResponse(
        string $recipient,
        string $subject,
        string $textBody,
        #[\SensitiveParameter]
        array $secrets = [],
    ): void {
        $this->responseSender->afterResponse(callback: fn() => $this->sendTextMail(
            recipient: $recipient,
            subject: $subject,
            textBody: $textBody,
            secrets: $secrets,
        ));
    }
}
