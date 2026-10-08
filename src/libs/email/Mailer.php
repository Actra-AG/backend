<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\ActraBackend;
use actra\yuf\mailer\SmtpMailer;
use actra\yuf\mailer\TextMail;

class Mailer
{
    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public static function sendTextMail(
        string $recipient,
        string $subject,
        string $textBody,
        ?string $replyTo = null,
        array $cc = [],
        array $bcc = [],
    ): void {
        $mailerSettings = ActraBackend::get()->mailerSettings;
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
        $textMail->send(
            abstractMailer: new SmtpMailer(
                serverAddress: Mailer::getServerAddress(),
                hostName: $mailerSettings->hostname,
                smtpUserName: $mailerSettings->username,
                smtpPassword: $mailerSettings->password,
                port: $mailerSettings->port,
                useTls: $mailerSettings->tls,
            ),
        );
    }

    /**
     * Names this server to the SMTP server: the address of the request, the host name without request (CLI).
     */
    private static function getServerAddress(): string
    {
        $serverAddress = ActraBackend::get()->findViewContext()?->httpRequest->getServerAddress();
        if ($serverAddress !== null && $serverAddress !== '') {
            return $serverAddress;
        }
        $hostName = gethostname();

        return $hostName === false ? 'localhost' : $hostName;
    }
}
