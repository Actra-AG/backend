# Plan: follow-up of the standard migration

Status: open. Points left after [../done/standard-migration/plan.md](../done/standard-migration/plan.md).

## Open points

- Browser check of all views, forms, tables, templates and emails in `../drogeriehaas.ch` (path repository),
  including the database updates `1.11.0.sql` and `2.0.0.sql` on a copy of real data and the mailer (`SmtpMailer` or
  `GraphMailer`). From v2.3.0 (yuf 5): login with password and with code, redirect to the requested page
  (`?returnTo=`), navigation, search forms with the compact renderer, CSV export of project tables.
- Frontend: [../frontend-coding-standard/plan.md](../frontend-coding-standard/plan.md), for the frontend developer.

## Handover

- 2026-10-09, v2.2.0: done: login and password reset mails after the response (response time), dates with
  `IntlDateFormatter` (`DateFormatter`, `LocalizedDateColumn`), `MailerSettings` with the mailer of the project
  (`GraphMailer` for Microsoft 365).
