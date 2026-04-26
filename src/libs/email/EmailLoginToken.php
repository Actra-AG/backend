<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthUser;

class EmailLoginToken
{
    public static function send(
        DbAuthUser $dbAuthUser,
        string $loginCode
    ): void {
        Mailer::sendTextMail(
            recipient: $dbAuthUser->email,
            subject: 'Backend - ' . $loginCode . ' ist ihr Bestätigungscode',
            textBody: implode(separator: PHP_EOL, array: [
                'Grüezi',
                '',
                'Mit dem nachfolgenden Bestätigungscode können Sie sich ohne Passwort sicher im Backend anmelden:',
                '',
                $loginCode,
                '',
                'Bitte beachten Sie, dass dieser Code nur einmal verwendet werden kann und nach 10 Minuten verfällt.',
                '',
                'Wenn Sie keinen Bestätigungscode für die E-Mail-Adresse ' . $dbAuthUser->email . ' angefordert haben, können Sie diese E-Mail ignorieren.',
                '',
                'Freundliche Grüsse',
                '',
                ActraBackend::get()->mailerSettings->signature,
            ])
        );
    }
}