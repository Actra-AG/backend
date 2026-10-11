<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Double;

use actra\yuf\common\EmailAddressError;
use actra\yuf\common\EmailAddressErrorEnum;
use actra\yuf\common\MailDomainResolver;
use Override;

/**
 * Rejects every mail domain, like a domain with a null MX in production.
 */
final readonly class RejectingMailDomainResolver implements MailDomainResolver
{
    #[Override]
    public function findProblem(string $domain): EmailAddressError
    {
        return new EmailAddressError(code: EmailAddressErrorEnum::NULL_MX, message: 'The domain accepts no mail.');
    }
}
