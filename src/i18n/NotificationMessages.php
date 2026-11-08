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

    public static function french(): NotificationMessages
    {
        return new NotificationMessages(
            title: 'Notifications',
            sendTitle: 'Envoyer une notification',
            sendInfo: 'Les notifications sont envoyées à une seconde d’intervalle. Veuillez ne cliquer qu’une seule '
                . 'fois sur «[send]».',
            sentSuccess: 'La notification a été envoyée.',
            detailsHeading: 'Détails',
            recipientsLabel: 'Destinataires',
            sentDateLabel: 'Date d’envoi',
            senderLabel: 'Expéditeur',
            senderDetailLabel: 'Expéditeur',
            messageLabel: 'Message',
            dateLabel: 'Date',
            userGroupRequired: 'Veuillez sélectionner un groupe d’utilisateurs.',
            defaultGreeting: 'Bonjour [firstName] [lastName]',
            defaultMessage: 'Message…',
        );
    }

    public static function italian(): NotificationMessages
    {
        return new NotificationMessages(
            title: 'Notifiche',
            sendTitle: 'Inviare una notifica',
            sendInfo: 'Le notifiche vengono inviate a un secondo di distanza l’una dall’altra. La preghiamo di '
                . 'cliccare una sola volta su «[send]».',
            sentSuccess: 'La notifica è stata inviata.',
            detailsHeading: 'Dettagli',
            recipientsLabel: 'Destinatari',
            sentDateLabel: 'Data di invio',
            senderLabel: 'Mittente',
            senderDetailLabel: 'Mittente',
            messageLabel: 'Messaggio',
            dateLabel: 'Data',
            userGroupRequired: 'Selezioni un gruppo di utenti.',
            defaultGreeting: 'Buongiorno [firstName] [lastName]',
            defaultMessage: 'Messaggio…',
        );
    }
}
