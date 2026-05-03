<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AccessRightCollection;
use actra\yuf\html\DetailDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneRenderer;
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
        public int $recipients
    ) {
    }
    
    public function render(): HtmlDataObjectCollection {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: 'ID',
                value: (string)$this->ID,
                isEncodedForRendering: true
            )
        );
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: 'Versanddatum',
                value: $this->sentDate->format(format: 'd.m.Y H:i:s'),
                isEncodedForRendering: true
            )
        );
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: 'Benutzergruppe',
                value: $this->groupName,
                isEncodedForRendering: true
            )
        );
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: 'Absender',
                value: $this->firstName.' '.$this->lastName,
                isEncodedForRendering: true
            )
        );
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: 'Betreff',
                value: $this->subject,
                isEncodedForRendering: true
            )
        );
        $htmlDataObjectCollection->add(
            htmlDataObject: new DetailDataObject(
                name: 'Mitteilung',
                value: nl2br(string: $this->message),
                isEncodedForRendering: true
            )
        );

        return $htmlDataObjectCollection;
    }
}