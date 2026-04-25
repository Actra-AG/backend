<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\ActraBackend;
use actra\yuf\mailer\SMTPMailer;
use actra\yuf\mailer\TextMail;

class Mailer
{
    public static function sendTextMail(
        string $recipient,
        string $subject,
        string $textBody,
        ?string $replyTo = null,
    ): void {
        $textMail = new TextMail(
            senderEmail: 'notification@actra.domains',
            fromEmail: 'notification@actra.domains',
            fromName: 'notification@actra.domains',
            toEmail: $recipient,
            toName: $recipient,
            subject: $subject,
            textBody: $textBody
        );
        if (!is_null(value: $replyTo)) {
            $textMail->addReplyTo(inputEmail: $replyTo);
        }
        $mailerSettings = ActraBackend::get()->mailerSettings;
        $textMail->send(
            new SMTPMailer(
                hostName: $mailerSettings->hostname,
                smtpUserName: $mailerSettings->username,
                smtpPassword: $mailerSettings->password,
                port: $mailerSettings->port,
                useTls: $mailerSettings->tls
            )
        );
    }
}