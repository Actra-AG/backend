<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\email;

use actra\backend\libs\email\Mailer;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\RecordingResponseSender;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    public function testSendAfterResponseOnlyRegistersTheMail(): void
    {
        $responseSender = new RecordingResponseSender();
        $mailer = new Mailer(
            mailerSettings: ActraBackendTestInstance::create()->mailerSettings,
            responseSender: $responseSender,
        );

        // The SMTP server of the test settings does not exist: sending now would throw
        $mailer->sendTextMailAfterResponse(recipient: 'user@example.com', subject: 'Code', textBody: '123456');

        $this->assertCount(1, $responseSender->afterResponseCallbacks);
    }
}
