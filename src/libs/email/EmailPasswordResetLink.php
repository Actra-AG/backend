<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\ActraBackend;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\view\backend\php\passwordReset;

/**
 * @internal
 */
final class EmailPasswordResetLink
{
    public static function send(
        DbAuthUser $dbAuthUser,
        string $token,
        int $expirationInMinutes,
    ): void {
        $httpRequest = ActraBackend::get()->getViewContext()->httpRequest;
        $host = $httpRequest->getHost();
        $messages = ActraBackend::messages();
        $emailMessages = $messages->email;
        Mailer::sendTextMail(
            recipient: $dbAuthUser->email,
            subject: $emailMessages->passwordResetSubject,
            textBody: implode(
                separator: PHP_EOL,
                array: [
                    $emailMessages->greeting,
                    '',
                    MessageTemplate::fill(
                        template: $emailMessages->passwordResetIntro,
                        values: ['host' => $host],
                    ),
                    '',
                    $emailMessages->passwordResetLinkInstruction,
                    $httpRequest->getProtocol()->value . '://' . $host . passwordReset::getPath(token: $token),
                    '',
                    MessageTemplate::fill(
                        template: $emailMessages->passwordResetValidity,
                        values: ['minutes' => (string) $expirationInMinutes],
                    ),
                    '',
                    MessageTemplate::fill(
                        template: $emailMessages->passwordResetIgnore,
                        values: ['email' => $dbAuthUser->email],
                    ),
                    '',
                    $messages->common->closingGreeting,
                    '',
                    ActraBackend::get()->mailerSettings->signature,
                ],
            ),
        );
    }
}
