<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\settings\MailerSettings;
use actra\yuf\core\HttpRequest;
use actra\yuf\mailer\SmtpMailer;
use actra\yuf\mailer\TextMail;

/**
 * Sends the emails of the backend with the SMTP settings of the project (`BackendViewContext::$mailer`).
 */
final readonly class Mailer
{
    /**
     * @param string $serverAddress Names this server to the SMTP server (see `getServerAddress()`)
     */
    public function __construct(
        public MailerSettings $mailerSettings,
        private string $serverAddress,
    ) {}

    /**
     * The address of the request, the host name without request (CLI).
     */
    public static function getServerAddress(?HttpRequest $httpRequest): string
    {
        $serverAddress = $httpRequest?->getServerAddress();
        if ($serverAddress !== null && $serverAddress !== '') {
            return $serverAddress;
        }
        $hostName = gethostname();

        return $hostName === false ? 'localhost' : $hostName;
    }

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
        $textMail->send(
            abstractMailer: new SmtpMailer(
                serverAddress: $this->serverAddress,
                hostName: $mailerSettings->hostname,
                smtpUserName: $mailerSettings->username,
                smtpPassword: $mailerSettings->password,
                port: $mailerSettings->port,
                useTls: $mailerSettings->tls,
            ),
        );
    }
}
