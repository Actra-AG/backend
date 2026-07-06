<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\ActraBackend;
use actra\backend\libs\db\DbAuthUser;
use actra\backend\view\backend\php\passwordReset;
use actra\yuf\core\HttpRequest;

class EmailPasswordResetLink
{
    public static function send(
        DbAuthUser $dbAuthUser,
        string $token,
        int $expirationInMinutes
    ): void {
        $host = HttpRequest::getHost();
        Mailer::sendTextMail(
            recipient: $dbAuthUser->email,
            subject: 'Ihr neues Passwort',
            textBody: implode(
                separator: PHP_EOL,
                array: [
                    'Grüezi',
                    '',
                    'Sie haben bei ' . $host . ' angegeben, dass Sie das Passwort vergessen haben.',
                    '',
                    'Klicken Sie auf den folgenden Link, um ein neues Passwort zu wählen:',
                    HttpRequest::getProtocol() . '://' . $host . passwordReset::getPath(token: $token),
                    '',
                    'Bitte beachten Sie, dass dieser Link nur einmal verwendet werden kann und nach ' . $expirationInMinutes . ' Minuten verfällt.',
                    '',
                    'Wenn Sie das Passwort für die E-Mail-Adresse ' . $dbAuthUser->email . ' nicht zurücksetzen möchten, können Sie diese E-Mail ignorieren.',
                    '',
                    'Freundliche Grüsse',
                    '',
                    ActraBackend::get()->mailerSettings->signature,
                ]
            )
        );
    }
}