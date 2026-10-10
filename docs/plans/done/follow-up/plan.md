# Plan: follow-up of the standard migration

Status: done (2026-10-10, v2.4.2). Points left after [../standard-migration/plan.md](../standard-migration/plan.md).

## Points

- [x] Browser check of all views, forms, tables, templates and emails in `../drogeriehaas.ch` (path repository),
  including the database updates `1.11.0.sql` and `2.0.0.sql` on a copy of real data and the mailer (`SmtpMailer` or
  `GraphMailer`). From v2.4.0 (yuf 5.1): password login, password + code, code login, a page without login → login →
  back to that page (both flows), user list and CSV export, visit filter, search forms, profile, API keys, navigation
  order.
- Frontend: [../../frontend-coding-standard/plan.md](../../frontend-coding-standard/plan.md), for the frontend
  developer (stays open).

## Handover

- 2026-10-09, v2.2.0: done: login and password reset mails after the response (response time), dates with
  `IntlDateFormatter` (`DateFormatter`, `LocalizedDateColumn`), `MailerSettings` with the mailer of the project
  (`GraphMailer` for Microsoft 365).
- 2026-10-10, v2.3.0–v2.4.2 (yuf 5.0–5.2): browser check done with the migration of `../drogeriehaas.ch` to
  actra/backend 2.4.2 and yuf 5.2 (its commits 024db46, b15e394, 63e61b5). Checked: password login, password + code,
  code login, a page without login → login → back to that page (both flows), user list and CSV export, visit filter,
  search forms, profile, API keys, navigation order, emails, the database updates `1.11.0.sql` and `2.0.0.sql` and the
  mailer. The bugs it found (401 after the login, lost `returnTo`, navigation order, search form markup) are fixed in
  v2.4.0 and v2.4.1. Left: only the decisions of the frontend developer in
  [../../frontend-coding-standard/plan.md](../../frontend-coding-standard/plan.md).
