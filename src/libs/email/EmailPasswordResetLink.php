<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\BackendViewContext;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\db\DbAuthUser;

/**
 * @internal
 */
final class EmailPasswordResetLink
{
    public static function send(
        BackendViewContext $context,
        DbAuthUser $dbAuthUser,
        string $token,
        int $expirationInMinutes,
    ): void {
        $httpRequest = $context->viewContext->httpRequest;
        $host = $httpRequest->getHost();
        $messages = $context->messages;
        $emailMessages = $messages->email;
        $context->mailer->sendTextMailAfterResponse(
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
                    $httpRequest->getProtocol()->value . '://' . $host . $context->paths->passwordReset(token: $token),
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
                    $context->mailer->mailerSettings->signature,
                ],
            ),
        );
    }
}
