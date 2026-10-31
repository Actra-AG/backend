<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\backend\libs\db;

use actra\yuf\db\DbDateTime;
use actra\yuf\db\DbQuery;
use actra\yuf\mailer\SentMail;

/**
 * The log of the sent emails (table `sent_emails`), written by `SentEmailLogger`.
 */
final readonly class DbSentEmailRepository
{
    /** Separates the addresses of a list column */
    public const string EMAIL_SEPARATOR = ', ';

    public function __construct(private DB $db) {}

    public function insert(SentMail $sentMail): void
    {
        $this->db->execute(
            sql: '
                INSERT INTO sent_emails
                SET sent_at=?,
                    from_email=?,
                    to_emails=?,
                    cc_emails=?,
                    bcc_emails=?,
                    subject=?
            ',
            parameters: [
                DbDateTime::toDateTimeString(dateTime: $sentMail->sentAt),
                $sentMail->fromEmail,
                implode(separator: DbSentEmailRepository::EMAIL_SEPARATOR, array: $sentMail->toEmails),
                implode(separator: DbSentEmailRepository::EMAIL_SEPARATOR, array: $sentMail->ccEmails),
                implode(separator: DbSentEmailRepository::EMAIL_SEPARATOR, array: $sentMail->bccEmails),
                $sentMail->subject,
            ],
        );
    }

    public function getDbQuery(): DbQuery
    {
        return DbQuery::createFromSqlQuery(
            query: '
                SELECT sent_emails.id,
                       sent_emails.sent_at,
                       sent_emails.from_email,
                       sent_emails.to_emails,
                       sent_emails.cc_emails,
                       sent_emails.bcc_emails,
                       sent_emails.subject
                FROM sent_emails
            ',
        );
    }
}
