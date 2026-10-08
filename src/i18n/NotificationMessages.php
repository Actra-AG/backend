<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\i18n;

/**
 * Texts of user notifications.
 * Defaults are English, german() returns the German variant. Override single texts with `with()`.
 */
final readonly class NotificationMessages
{
    use OverridableMessages;

    public function __construct(
        public string $title = 'Notifications',
        public string $sendTitle = 'Send notification',
        public string $sendInfo = 'The notifications are sent one second apart. Please do not click "[send]" more than '
            . 'once.',
        public string $sentSuccess = 'The notification has been sent.',
        public string $detailsHeading = 'Details',
        public string $recipientsLabel = 'Recipients',
        public string $sentDateLabel = 'Sent on',
        public string $senderLabel = 'Sender',
        public string $senderDetailLabel = 'Sender',
        public string $messageLabel = 'Message',
        public string $dateLabel = 'Date',
        public string $userGroupRequired = 'Please select a user group.',
        public string $defaultGreeting = 'Hello [firstName] [lastName]',
        public string $defaultMessage = 'Message...',
    ) {}

    public static function german(): NotificationMessages
    {
        return new NotificationMessages(
            title: 'Benachrichtigungen',
            sendTitle: 'Benachrichtigung senden',
            sendInfo: 'Die Benachrichtigungen werden mit einem Abstand von einer Sekunde verschickt. Bitte klicken Sie '
                . 'nicht mehrfach auf "[send]".',
            sentSuccess: 'Die Benachrichtigung wurde verschickt.',
            detailsHeading: 'Details',
            recipientsLabel: 'Empfänger',
            sentDateLabel: 'Versanddatum',
            senderLabel: 'Sender',
            senderDetailLabel: 'Absender',
            messageLabel: 'Mitteilung',
            dateLabel: 'Datum',
            userGroupRequired: 'Bitte wählen Sie eine Benutzergruppe aus.',
            defaultGreeting: 'Guten Tag [firstName] [lastName]',
            defaultMessage: 'Nachricht...',
        );
    }
}
