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
final class EmailLoginToken
{
    public static function send(
        BackendViewContext $context,
        DbAuthUser $dbAuthUser,
        string $token,
        int $expirationInMinutes,
        bool $usedPasswordLogin,
    ): void {
        $messages = $context->messages;
        $emailMessages = $messages->email;
        $context->mailer->sendTextMail(
            recipient: $dbAuthUser->email,
            subject: MessageTemplate::fill(
                template: $emailMessages->loginTokenSubject,
                values: ['token' => $token],
            ),
            textBody: implode(
                separator: PHP_EOL,
                array: [
                    $emailMessages->greeting,
                    '',
                    $usedPasswordLogin
                        ? $emailMessages->loginTokenIntroPasswordLogin
                        : $emailMessages->loginTokenIntroPasswordless,
                    '',
                    $token,
                    '',
                    MessageTemplate::fill(
                        template: $emailMessages->loginTokenValidity,
                        values: ['minutes' => (string) $expirationInMinutes],
                    ),
                    '',
                    MessageTemplate::fill(
                        template: $emailMessages->loginTokenIgnore,
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
