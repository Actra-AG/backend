<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\email;

use actra\backend\libs\db\DbAuthUser;

class EmailAuthUser
{
    public static function send(
        DbAuthUser $dbAuthUser,
        string $subject,
        string $message
    ): void {
        Mailer::sendTextMail(
            recipient: $dbAuthUser->email,
            subject: $subject,
            textBody: $message
        );
    }
}