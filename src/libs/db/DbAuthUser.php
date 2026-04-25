<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\auth\AccessRightCollection;
use DateTimeImmutable;

readonly class DbAuthUser
{
    public function __construct(
        public int $ID,
        public DateTimeImmutable $registered,
        public ?DateTimeImmutable $invitedDate,
        private ?DateTimeImmutable $lastLogin,
        public string $email,
        public bool $isActive,
        public AccessRightCollection $accessRightCollection,
        public string $firstName,
        public string $lastName
    ) {
        $this->accessRightCollection->add(accessRight: AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN);
    }

    public function isInvited(): bool
    {
        return $this->invitedDate !== null;
    }

    public function renderLastLogin(): string
    {
        return $this->lastLogin === null ? '' : $this->lastLogin->format(format: 'd.m.Y H:i:s');
    }

    public function renderActive(): string
    {
        return $this->isActive ? 'aktiv' : 'inaktiv';
    }
}