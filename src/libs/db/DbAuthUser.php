<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\backend\ActraBackend;
use actra\yuf\auth\AccessRightCollection;
use actra\yuf\auth\Password;
use actra\yuf\html\HtmlDataObject;
use actra\yuf\html\HtmlDataObjectCollection;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneRenderer;
use DateTimeImmutable;

readonly class DbAuthUser
{
    /** @var list<string> */
    public array $ipWhitelist;

    public function __construct(
        public int $ID,
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
        string $rawIpWhitelist,
    ) {
        if ($this->password !== null) {
            $this->accessRightCollection->add(accessRight: AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN);
        }
        $ipWhitelist = [];
        if ($rawIpWhitelist !== '') {
            foreach (
                explode(
                    separator: ',',
                    string: $rawIpWhitelist,
                ) as $ipAddress
            ) {
                if ($ipAddress === '') {
                    continue;
                }
                $ipWhitelist[] = $ipAddress;
            }
        }
        $this->ipWhitelist = $ipWhitelist;
    }

    public function isInvited(): bool
    {
        return $this->invitedDate !== null;
    }

    public function renderLastLogin(): string
    {
        if ($this->lastLogin === null) {
            return '';
        }

        return $this->lastLogin->format(format: ActraBackend::messages()->common->dateTimeFormat);
    }

    public function renderActive(): string
    {
        return $this->isActive
            ? ActraBackend::messages()->user->statusActive
            : ActraBackend::messages()->user->statusInactive;
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
