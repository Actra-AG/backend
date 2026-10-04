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

class EmailLoginToken
{
    public static function send(
        DbAuthUser $dbAuthUser,
        string $token,
        int $expirationInMinutes,
        bool $usedPasswordLogin
    ): void {
        $messages = ActraBackend::messages();
        $emailMessages = $messages->email;
        Mailer::sendTextMail(
            recipient: $dbAuthUser->email,
            subject: MessageTemplate::fill(
                template: $emailMessages->loginTokenSubject,
                values: ['token' => $token]
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
                        values: ['minutes' => (string)$expirationInMinutes]
                    ),
                    '',
                    MessageTemplate::fill(
                        template: $emailMessages->loginTokenIgnore,
                        values: ['email' => $dbAuthUser->email]
                    ),
                    '',
                    $messages->common->closingGreeting,
                    '',
                    ActraBackend::get()->mailerSettings->signature,
                ]
            )
        );
    }
}