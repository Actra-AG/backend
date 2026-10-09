# Plan: follow-up of the standard migration

Status: open. Points left after [../done/standard-migration/plan.md](../done/standard-migration/plan.md) (v2.0.0),
not started yet.

## Open points

- Browser check of all views, forms, tables and templates in `../drogeriehaas.ch` (path repository), including the
  database update `1.11.0.sql` and `2.0.0.sql` on a copy of real data.
- Token login: the response time can tell whether an email address exists (no email sent for unknown addresses);
  sending the email after the response would close it.
- Dates with `IntlDateFormatter` instead of the per-language patterns (needs own table columns).
- Microsoft Graph mailer (yuf v4.56) for Microsoft 365 without SMTP basic auth.
- JavaScript as plain files concatenated by the project instead of ES modules (coding standard v1.11.0,
  `html-javascript.md`, section 2); changes the shipped JavaScript, decision open.
