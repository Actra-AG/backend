<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\i18n\BackendMessages;
use actra\backend\i18n\MessageTemplate;
use actra\backend\libs\common\DateFormatter;
use actra\yuf\html\DetailDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;

final readonly class DbAuthUserNotification
{
    public function __construct(
        public int $id,
        public int $authGroupId,
        public int $sentById,
        public DateTimeImmutable $sentDate,
        public string $subject,
        public string $message,
        public string $groupName,
        public string $firstName,
        public string $lastName,
        public int $recipients,
    ) {}

    public function render(BackendMessages $messages, DateFormatter $dateFormatter): HtmlDataObjectCollection
    {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        $details = [
            ['ID', (string) $this->id],
            [
                $messages->notification->sentDateLabel,
                $dateFormatter->formatDateTime(dateTime: $this->sentDate),
            ],
            [$messages->common->userGroupLabel, $this->groupName],
            [
                $messages->notification->senderDetailLabel,
                MessageTemplate::fill(
                    template: $messages->common->fullName,
                    values: ['firstName' => $this->firstName, 'lastName' => $this->lastName],
                ),
            ],
            [$messages->common->subjectLabel, $this->subject],
        ];
        foreach ($details as [$label, $value]) {
            $htmlDataObjectCollection->add(
                htmlDataObject: new DetailDataObject(
                    name: HtmlText::fromText(text: $label),
                    value: HtmlText::fromText(text: $value),
                ),
            );
        }
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: HtmlText::fromText(text: $messages->notification->messageLabel),
                value: HtmlText::fromTextWithLineBreaks(text: $this->message),
            ),
        );

        return $htmlDataObjectCollection;
    }
}
