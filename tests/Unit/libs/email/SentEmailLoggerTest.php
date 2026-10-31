<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\tests\Unit\libs\email;

use actra\backend\libs\db\BackendRepositories;
use actra\backend\libs\db\DB;
use actra\backend\libs\email\SentEmailLogger;
use actra\backend\tests\Double\ActraBackendTestInstance;
use actra\backend\tests\Double\RecordingLogger;
use actra\backend\tests\Double\RecordingResponseSender;
use actra\backend\tests\Double\TestDatabase;
use actra\yuf\db\DbRow;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\mailer\SentMail;
use actra\yuf\mailer\TextMail;
use DateTimeImmutable;
use PDOException;
use PHPUnit\Framework\TestCase;

final class SentEmailLoggerTest extends TestCase
{
    public function testEveryMailOfTheMailerIsLogged(): void
    {
        $db = TestDatabase::connect();
        $recordingMailer = new RecordingMailer();
        $actraBackend = ActraBackendTestInstance::create(dbSettings: TestDatabase::settings(), mailer: $recordingMailer);
        $subject = 'Code ' . bin2hex(string: random_bytes(length: 4));

        $actraBackend->createMailer(responseSender: new RecordingResponseSender())->sendTextMail(
            recipient: 'User@Example.com',
            subject: $subject,
            textBody: '123456',
            cc: ['cc1@example.com', 'cc2@example.com'],
            bcc: ['bcc@example.com'],
        );

        $row = $db->selectRow(sql: 'SELECT * FROM sent_emails WHERE subject=?', parameters: [$subject]);
        $this->assertInstanceOf(DbRow::class, $row);
        $this->assertSame('backend@example.com', $row->getString(column: 'from_email'));
        $this->assertSame('user@example.com', $row->getString(column: 'to_emails'));
        $this->assertSame('cc1@example.com, cc2@example.com', $row->getString(column: 'cc_emails'));
        $this->assertSame('bcc@example.com', $row->getString(column: 'bcc_emails'));
        $this->assertEqualsWithDelta(
            time(),
            $row->getDateTimeImmutable(column: 'sent_at')->getTimestamp(),
            60,
        );
    }

    /**
     * The mail is sent when the log entry fails: the error is logged, `send()` does not throw (no second sending).
     */
    public function testDatabaseErrorIsLoggedInsteadOfThrown(): void
    {
        $recordingLogger = new RecordingLogger();
        $recordingMailer = new RecordingMailer();
        $recordingMailer->addSentMailListener(sentMailListener: new SentEmailLogger(
            repositories: new BackendRepositories(
                connect: static fn(): DB => throw new PDOException(message: 'Database unreachable'),
            ),
            logger: $recordingLogger,
        ));
        $textMail = new TextMail(
            senderEmail: 'backend@example.com',
            fromEmail: 'backend@example.com',
            fromName: 'Backend',
            toEmail: 'user@example.com',
            toName: 'User',
            subject: 'Code',
            textBody: '123456',
        );

        $textMail->send(abstractMailer: $recordingMailer);

        $this->assertCount(1, $recordingMailer->sentMails);
        $this->assertCount(1, $recordingLogger->exceptions);
        $this->assertSame('Database unreachable', $recordingLogger->exceptions[0]->getMessage());
    }

    public function testQueryErrorIsLogged(): void
    {
        $recordingLogger = new RecordingLogger();
        $repositories = BackendRepositories::fromDb(db: TestDatabase::connect());
        $sentEmailLogger = new SentEmailLogger(repositories: $repositories, logger: $recordingLogger);

        // Longer than from_email (varchar(254)): rejected in the strict SQL mode of MariaDB
        $sentEmailLogger->onMailSent(sentMail: new SentMail(
            sentAt: new DateTimeImmutable(),
            fromEmail: str_repeat(string: 'a', times: 300) . '@example.com',
            toEmails: ['user@example.com'],
            ccEmails: [],
            bccEmails: [],
            subject: 'Too long',
        ));

        $this->assertCount(1, $recordingLogger->exceptions);
    }
}
