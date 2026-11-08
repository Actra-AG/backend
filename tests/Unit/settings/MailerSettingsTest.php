<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\settings;

use actra\backend\i18n\EmailMessages;
use actra\backend\settings\MailerSettings;
use actra\yuf\clock\SystemClock;
use actra\yuf\mailer\RecordingMailer;
use PHPUnit\Framework\TestCase;

/**
 * The signature of the emails is a text of the language of the email (`EmailMessages::$signature`).
 */
final class MailerSettingsTest extends TestCase
{
    public function testSignatureIsTheSenderNameByDefault(): void
    {
        $this->assertSame('Example team', $this->createMailerSettings()->createSignature(
            emailMessages: EmailMessages::french(),
        ));
    }

    public function testSignatureFollowsTheMessagesOfTheLanguage(): void
    {
        $mailerSettings = $this->createMailerSettings();

        $this->assertSame("Example team\nwww.example.com", $mailerSettings->createSignature(
            emailMessages: EmailMessages::german()->with(signature: "[senderName]\nwww.example.com"),
        ));
        $this->assertSame("L’équipe Example\nwww.example.com", $mailerSettings->createSignature(
            emailMessages: EmailMessages::french()->with(signature: "L’équipe Example\nwww.example.com"),
        ));
    }

    private function createMailerSettings(): MailerSettings
    {
        return new MailerSettings(
            senderEmail: 'noreply@example.com',
            senderName: 'Example team',
            mailer: new RecordingMailer(clock: new SystemClock()),
        );
    }
}
