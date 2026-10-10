# Plan: yuf ~5.1.0, Composer only, bugs from drogeriehaas.ch

Status: done (2026-10-10, v2.4.0). One release for all items; no backwards compatibility layers.

## Items

| Item | Status |
|:--|:--|
| 1. yuf ~5.1.0, coding standard ^1.20.0, Composer only, strict PSR-4 | done |
| 1. Tests: `$this->assert*()`, static `fail()`/`markTestSkipped()` | done |
| 2.1 401 after a successful login (current user read before the login) | done |
| 2.2 `returnTo` lost between the login steps | done |
| 2.3 Navigation order: project items before "users" (as in v2.2) | done |
| 2.4 German subject of the login code mail: "Ihr" | done |
| 2.5 B-4 `<label for>` of the search fields: `CompactFieldRenderer` (HTML/CSS change accepted by the user) | done |
| 3. `LocalizedDateColumn` / `DateFormatter` vs. `DateColumn::useLocale()` | done |
| 3. `MyAuthenticator::$user` | done |
| 3. Re-check A1–A15 | done |
| 4. Docs: `vendor/autoload.php` in entry points, deployment; AGENTS.md | done |
| 5. CLI test-session tool, ApiView base class | dropped (user, 2026-10-10) |
| 6. Check, plan to done, report | done |

## Handover notes

### All items (2026-10-10, v2.4.0)

- 1: `actra/autoloader` removed by Composer (yuf 5.1 no longer requires it), `--strict-psr` without warnings (the
  lowercase view classes match their file names). Tests use `$this->assert*()`.
- 2.1: `LoginTokenForm::process()` returns the logged-in `MyAuthUser` (`MyAuthenticator::tokenLogin()` loads it after
  the login); the views redirect with it. All redirects pass the request's `ResponseSender`, so `LoginRedirectTest`
  runs both flows through `ContentHandler::processRequest()` and checks every 303 (fails with the 401 without the fix).
- 2.2: yuf's forms post to `?<form name>` and drop `returnTo`, and yuf has no form action option: the three login
  forms carry the validated return path in a hidden field (`ReturnPathField`, own fields, no copy of yuf code).
- 2.3: project items first (`NavigationTest`). 2.4: "Ihr" in two German texts (`MessagesTest` checks the formal
  address). 2.5: `CompactFieldRenderer` for the search forms (`SearchFormTest`); in this setup the ids equal the field
  names, so the label/id check guards against regressions.
- 3: `DateColumn::useLocale()` gives the same output as `DateFormatter` (`DateColumnTest`), so `LocalizedDateColumn` is
  removed (`AbstractTable::createDateColumn()`). `DateFormatter` stays for single dates in views and emails: yuf
  formats dates for a locale only in `DateColumn`. `MyAuthenticator::$user` removed. No workaround A1–A15 left; the
  remaining `explode()` calls parse request values (navigation levels, URI, bearer token).
- 4: README (entry point, deployment, one login path for all language routes), AGENTS.md.
- Frontend review: no template or CSS file changed. Generated HTML: search forms (`<div class="form-compact-field">`),
  hidden field `returnTo` in the four login forms.
