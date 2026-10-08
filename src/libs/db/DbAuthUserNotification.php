<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\ActraBackend;
use actra\yuf\html\DetailDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlEncoder;
use DateTimeImmutable;

readonly class DbAuthUserNotification
{
    public function __construct(
        public int $ID,
        public int $authGroupID,
        public int $sentByID,
        public DateTimeImmutable $sentDate,
        public string $subject,
        public string $message,
        public string $groupName,
        public string $firstName,
        public string $lastName,
        public int $recipients,
    ) {}

    public function render(): HtmlDataObjectCollection
    {
        $messages = ActraBackend::messages();
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        $details = [
            ['ID', (string) $this->ID],
            [
                $messages->notification->sentDateLabel,
                $this->sentDate->format(format: $messages->common->dateTimeFormat),
            ],
            [$messages->common->userGroupLabel, $this->groupName],
            [$messages->notification->senderDetailLabel, $this->firstName . ' ' . $this->lastName],
            [$messages->common->subjectLabel, $this->subject],
        ];
        foreach ($details as [$label, $value]) {
            $htmlDataObjectCollection->add(
                htmlDataObject: DbAuthUserNotification::createDetail(
                    label: $label,
                    valueHtml: HtmlEncoder::encode(value: $value),
                ),
            );
        }
        $htmlDataObjectCollection->add(
            htmlDataObject: DbAuthUserNotification::createDetail(
                label: $messages->notification->messageLabel,
                valueHtml: nl2br(string: HtmlEncoder::encode(value: $this->message)),
            ),
        );

        return $htmlDataObjectCollection;
    }

    /**
     * yuf's DetailDataObject never encodes the label, so both parts are passed as HTML.
     */
    private static function createDetail(string $label, string $valueHtml): DetailDataObject
    {
        return new DetailDataObject(
            name: HtmlEncoder::encode(value: $label),
            value: $valueHtml,
            isEncodedForRendering: true,
        );
    }
}
