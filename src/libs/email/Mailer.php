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
        $mailerSettings = ActraBackend::get()->mailerSettings;
        $textMail = new TextMail(
            senderEmail: $mailerSettings->senderEmail,
            fromEmail: $mailerSettings->senderEmail,
            fromName: $mailerSettings->senderEmail,
            toEmail: $recipient,
            toName: $recipient,
            subject: $subject,
            textBody: $textBody
        );
        if ($replyTo !== null) {
            $textMail->addReplyTo(inputEmail: $replyTo);
        }
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