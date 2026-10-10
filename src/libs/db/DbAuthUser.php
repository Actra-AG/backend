<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\i18n\CommonMessages;
use actra\backend\i18n\MessageTemplate;
use actra\backend\i18n\UserMessages;
use actra\backend\libs\common\DateFormatter;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\Password;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneRenderer;
use DateTimeImmutable;

final readonly class DbAuthUser
{
    /**
     * @param list<string> $ipWhitelist
     */
    public function __construct(
        public int $id,
        public DateTimeImmutable $registered,
        public ?DateTimeImmutable $invitedDate,
        private ?DateTimeImmutable $lastLogin,
        public string $email,
        public string $phone,
        public bool $isActive,
        public AccessRightCollection $accessRightCollection,
        public string $firstName,
        public string $lastName,
        public ?string $languageCode,
        public ?Password $password,
        public int $wrongLoginAttempts,
        public array $ipWhitelist,
    ) {}

    public function isInvited(): bool
    {
        return $this->invitedDate !== null;
    }

    public function renderFullName(CommonMessages $messages): string
    {
        return MessageTemplate::fill(
            template: $messages->fullName,
            values: ['firstName' => $this->firstName, 'lastName' => $this->lastName],
        );
    }

    public function renderLastLogin(DateFormatter $dateFormatter): string
    {
        if ($this->lastLogin === null) {
            return '';
        }

        return $dateFormatter->formatDateTime(dateTime: $this->lastLogin);
    }

    public function renderActive(UserMessages $messages): string
    {
        return $this->isActive
            ? $messages->statusActive
            : $messages->statusInactive;
    }

    public function renderPhone(): string
    {
        if ($this->phone === '') {
            return '';
        }
        return PhoneRenderer::renderInternationalFormat(
            phoneNumber: PhoneNumber::createFromString(
                input: $this->phone,
                defaultCountryCode: 'CH',
            ),
        );
    }

    public function renderIpWhitelist(): HtmlDataObjectCollection
    {
        $htmlDataObjectCollection = new HtmlDataObjectCollection();
        foreach ($this->ipWhitelist as $ip) {
            $htmlDataObject = new HtmlDataObject();
            $htmlDataObject->addText(
                propertyName: 'ipAddress',
                text: $ip,
            );
            $htmlDataObjectCollection->add(htmlDataObject: $htmlDataObject);
        }
        return $htmlDataObjectCollection;
    }
}
