# Security audit of actra/backend and actra/yuf (2026-10-10)

Full audit of `actra/backend` (HEAD `4affe0b`, v2.6.0) and `actra/yuf` (HEAD `f0c9795`, v5.6.0; the backend's
`vendor/actra/yuf` is v5.5.0 and byte-identical to that tag). Static review of all PHP, templates, JS, CSS, SQL and
seed data, git history and supply chain, plus PoC scripts (DDEV) and black-box probes of the local consumer
`../drogeriehaas.ch`.

> ⚠️ Commit and push this file only together with the fixes (yuf v6.0.0, backend v2.7.0): it describes the
> vulnerabilities in detail.

## Result

- **No backdoor found.** No obfuscated code, no hidden parameters/headers/cookies, no hard-coded credentials or
  tokens in code, no debug bypass, no exfiltration or telemetry, no prompt injection in `AGENTS.md`/docs. Superglobals
  are read only in `HttpRequest::fromGlobals()`; client IP is `REMOTE_ADDR` only. All commits by known Actra
  identities; tags reachable from `main`; no hidden objects beyond stashes/amends.
- **Supply chain clean.** `vendor/actra/yuf` = tag v5.5.0, `vendor/actra/coding-standard` = tag v1.23.0, all 69
  locked packages from their upstream GitHub repos, no Composer plugins or package scripts. Phone metadata files
  (254) contain only literal arrays (token scan).
- **Real vulnerabilities:** 2 high, 6 medium in the backend; 1 medium (conditional) and several low in yuf. Details
  below. "PoC" = proven by a script in DDEV; "verified" = re-checked in the code by the reviewing session.

## Immediate actions (no code, before the release)

1. Check every installation of `actra/backend` for the seeded user `admin@actra.ch` (`SELECT id, email, active FROM
   auth_user WHERE email='admin@actra.ch'`). Delete it or give it the project's own address (B-2).
2. List API keys of inactive users or users without groups and delete them (B-1):
   `SELECT k.* FROM auth_api_key k JOIN auth_user u ON u.id=k.user_id WHERE u.active=0 OR NOT EXISTS (SELECT 1 FROM
   auth_user_group g WHERE g.user_id=u.id)`.
3. Check that `allowedDomains` in every `.env.php` lists only domains you control (it is what blocks Host header
   poisoning of reset links, B-14) and that `debug` is `false` in production.
4. Delete the leftover `../yuf/key.pem`, `cert.pem` (expired self-signed localhost key, never committed), `tmp/`,
   `zz-old/`.

## Findings: actra/backend

| ID   | Sev    | Finding                                                                                                                                                                                                                                                       | Where                                                                                                          | Proof    |
|------|--------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------|----------|
| B-1  | high   | API key login ignores `active`, rights, the user's IP whitelist (which the docs require for keys) and `hasApi`. Deactivated / offboarded users keep API access from any IP.                                                                                | `src/libs/db/DbAuthApiKeyRepository.php:95-115`                                                                | PoC, verified |
| B-2  | high   | `db/data.sql` seeds an active Administrator `admin@actra.ch` without password; login is by emailed code, so whoever reads that mailbox is admin on every installation that kept the row. README calls it "a test user account".                         | `db/data.sql:91-105`, `README.md:62`                                                                           | verified |
| B-3  | medium | Password reset token is never claimed: the same link sets the password again and again until it expires; other sessions stay alive.                                                                                                                          | `src/libs/form/PasswordResetForm.php:73-84`, `src/view/backend/php/passwordReset.php:52-58`                    | PoC, verified |
| B-4  | medium | Password reset token: 6 chars of 36 (~31 bit), in the URL, not session-bound, no attempt limit; one guess tests all users' open tokens (404 vs 200). Brute force feasible in hours against many targets.                                                  | `src/libs/db/DbAuthTokenRepository.php:20-21,40-43,99-131`, `passwordReset.php:52`                             | calculated |
| B-5  | medium | Wrong-password counter is never reset on successful login: typos accumulate over the account's lifetime, after 5 the user is locked permanently (until reset). Anyone knowing an email locks the account with 5 requests.                                 | `src/libs/db/DbAuthUserRepository.php:164-177` (yuf `AuthUser::confirmSuccessfulLogin` expects the DB reset)    | PoC, verified |
| B-6  | medium | Send limit of v2.6.0 is per user only: 5 requests per 15 min by anyone block a user's login codes / reset links (permanent lockout together with B-5). Count and insert are not atomic (parallel requests exceed the limit).                              | `src/libs/auth/AuthTokens.php:30-33,69-81`, `DbAuthTokenRepository::countRegisteredWithin`                     | PoC      |
| B-7  | medium | User IP whitelist is merged into the global whitelist: a user can widen access beyond the operator's list (own whitelist editable in profile), and after login the user's own list no longer restricts.                                                   | `src/BackendView.php:69-90`, `src/libs/form/ProfileForm.php:117-182`                                           | PoC      |
| B-8  | medium | No rights hierarchy for `manage_users`: may grant any group (also project rights it does not have), change admins' emails (= takeover via email login), impersonate admins, delete/deactivate itself or the last admin.                                  | `UserModForm.php:107-118,188-193`, `UserAddForm`, `MyAuthUser::canImpersonateUser` (`:119-135`), `userDelete.php` | code     |
| B-9  | low/med| Changing, resetting or removing a password does not end other sessions or delete open tokens; session id not regenerated.                                                                                                                                    | `ProfilePasswordForm::process`, `PasswordResetForm`, `DbAuthUserRepository::setPassword/removePassword`         | code     |
| B-10 | low    | State changes by GET without CSRF token: `?impersonate`, `?cancelSessionChange`, logout. Protected only by `SameSite=Strict`, which yuf's Microsoft SSO downgrades to `None` (Y-6).                                                                       | `src/view/backend/php/user.php:96-144`, `src/BackendView.php:110-125`, `logout.php`                            | code     |
| B-11 | low    | Impersonation session is not re-checked against the impersonator (deactivated or demoted admin keeps the impersonated session).                                                                                                                              | `src/BackendView.php:71-80`                                                                                     | code     |
| B-12 | low    | One-time tokens stored in plain text, shown and searchable in the token log; login code kept in plain text in the session; compared with `!==`; `>` allows max+1 attempts.                                                                                 | `DbAuthTokenRepository.php:41-62`, `libs/table/TokenTable.php:118-124`, `libs/auth/AuthTokens.php:45,85-92`     | verified |
| B-13 | low    | Current-password check in the profile has no attempt limit (online guessing with a stolen session).                                                                                                                                                          | `src/libs/form/ProfilePasswordForm.php:94-104`                                                                 | code     |
| B-14 | low    | Absolute URLs from the Host header: reset mail link, invite text, profile link (profile adds it with `addHtml` → raw HTML, PoC). Mitigated by yuf's strict `allowedDomains` check (black-box: foreign Host → 404).                                       | `src/libs/email/EmailPasswordResetLink.php:24-46`, `src/view/backend/php/profile.php:99-103`, `UserInviteForm.php:67-73` | PoC (render) |
| B-15 | low    | No server-side length limits in user/profile forms: overlong input gives DB errors (500) instead of form errors (yuf `maxLength` is only an HTML attribute, Y-8).                                                                                          | `UserModForm.php`, `UserAddForm.php`, `ProfileForm.php`                                                        | PoC      |
| B-16 | info   | Message texts passed as raw HTML to yuf navigation and column labels (safe today, XSS once messages come from a DB/translation service); invalid stored phone crashes `user.php` (`PhoneParseException`); `utf8mb4_unicode_ci` on `auth_api_key.public_id` / `auth_token.token`; small timing differences between known/unknown emails in token forms. | `ActraBackend::createNavigation()`, tables, `DbAuthUser::renderPhone()`, `db/schema.sql`                        | code     |

Checked and OK (backend): access rights of every view (login/reset flow only public), IDOR (all user-id views need
`manage_users`), all SQL bound (only constant concatenation), XSS (payloads in all stored fields rendered in all
views: always encoded), JS (no `innerHTML`/`eval`, same-origin fetch only), mails are text only, open redirect
(`LoginRedirect::isLocalPath`), login code (CSPRNG, 15 min, single use, session-bound, attempt counter), password
login timing, session regeneration on login/logout, API key secret (32 bytes, SHA-256, `hash_equals`), user deletion
cleans everything, `BackendViewFactory` class names (PHP rejects invalid names before autoload).

## Findings: actra/yuf

| ID   | Sev    | Finding                                                                                                                                                                                                                                                       | Where                                                                                     | Proof |
|------|--------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------|-------|
| Y-1  | medium | Path traversal into `require` of `*.lang.php`: in routes with path variables (`/shop/${fileName}`) the variable matches `(.*)` incl. `../`; `Route::loadLocalizedText()` builds `language/<code>/<fileTitle>.lang.php` unchecked → LFI, RCE if an attacker can place a `.lang.php` file. Backend routes not affected. | `src/core/Route.php:60-62`, `src/core/LocaleHandler.php:86`, `RequestHandler.php:248`      | PoC   |
| Y-2  | medium | Microsoft SSO identifies the user by the mutable, unverified `email` claim (tenant admins / B2B guests can set any `mail`).                                                                                                                                    | `src/auth/MicrosoftIdToken.php:62-71`, `Authenticator.php:362-371`                        | design |
| Y-3  | low    | Wrong-password lock-out is racy: the counter is read once per request, parallel requests without session get as many guesses as PHP workers.                                                                                                                | `src/auth/Authenticator.php:279-282,305`                                                  | code  |
| Y-4  | low    | Empty SSO nonce accepted (`'' === ''`): login CSRF / replay when the project lost the nonce.                                                                                                                                                                  | `src/auth/MicrosoftIdToken.php:94,119-125`, `MicrosoftAuthenticator.php:82-108`           | PoC   |
| Y-5  | low    | Timing: legacy SHA-256 users answer ~50 ms faster (reveals accounts with weak legacy hashes); without Argon2 the dummy hash fails instantly (user enumeration).                                                                                               | `src/auth/Password.php:41-51,74-82`                                                       | code  |
| Y-6  | low    | `changeCookieSameSiteToNone()` leaves the session cookie at `SameSite=None` if the SSO login is abandoned.                                                                                                                                                    | `src/session/AbstractSessionHandler.php:533-553`, `MicrosoftAuthenticator.php:69`        | PoC   |
| Y-7  | low    | CSRF token not renewed on login / impersonation.                                                                                                                                                                                                              | `src/auth/AuthSession.php:28-38`                                                          | code  |
| Y-8  | low    | `maxLength` of input fields is only rendered as attribute, never validated.                                                                                                                                                                                   | `src/form/component/field/InputField.php:27`, `renderer/InputFieldRenderer.php:61-65`     | PoC   |
| Y-9  | low    | Form side effects (upload storage, `_removeAttachment`, listeners) run before the CSRF check; `FormContext` without token source silently disables CSRF.                                                                                                     | `src/form/component/collection/Form.php:67-71,181-197`                                    | code  |
| Y-10 | low    | Upload pointer not bound to the session: a posted foreign pointer lets `clearData()` delete another user's uploads. Upload root defaults to `sys_get_temp_dir()/<SERVER_NAME>` (Host-derived, shared, `mkdir` 0777, symlink not checked).                  | `FileField.php:157-163,359-363`, `upload/SessionFileUploadStorage.php:50-69,118,148-172`  | PoC   |
| Y-11 | low    | STARTTLS: bytes sent before the TLS handshake stay in the stream buffer and are read as TLS replies (MITM can fake capabilities / "250 OK").                                                                                                                  | `src/mailer/SmtpMailer.php:221-235`, `StreamSmtpTransport::enableTls()` `:139-157`        | PoC   |
| Y-12 | low    | SMTP `AUTH` allowed without TLS; no implicit TLS (465).                                                                                                                                                                                                        | `src/mailer/SmtpMailer.php:85,172-178`                                                    | code  |
| Y-13 | low    | HTTP→HTTPS redirect uses the Host header before the `allowedDomains` check (cache poisoning behind a shared cache).                                                                                                                                         | `src/Core.php:308-315`                                                                    | code  |
| Y-14 | low    | Log/mail flood: ticket hash includes the exception message; `LoginRedirect::isLocalPath()` accepts `/x:80` which `parse_url()` rejects → 500 with the input in the message (one ticket + mail per value).                                                  | `src/core/FileLogger.php:91-109`, `src/core/LoginRedirect.php`, `src/common/UrlHelper.php:32-36` | PoC |
| Y-15 | low    | File responses: HTML/XML/JS inline by default without CSP (stored XSS if projects serve uploads); `Content-Disposition` filename not escaped.                                                                                                               | `src/common/FileHandler.php:82`, `src/core/HttpResponse.php:343,369`                      | code  |
| Y-16 | info   | Mail addresses with quoted local parts accept NUL/VT/`<>`; `SearchQueryBuilder::createSqlFilters()` puts keys unchecked into SQL (internal callers only); `TableItem::renderValue()` keeps quotes; raw HTML in column labels, CSS classes, `ActionsColumn` link targets (a leading placeholder allows `javascript:`), `NavigationItem` title; stored sort column not re-checked; `PhoneNumberField::renderValue()` unencoded; spam token replayable for `maxSeconds`; log injection via multi-line request values; exception traces may contain arguments; no `form-action` in the CSP; session GC 1/100000 with own save path; `FileSessionHandler` ignores `N;` in `save_path`; `CachedKeySet` without throttle when no cache exists; `.env.example.php` ships `debug => true`; stale docs (`FormInput.php:44` query CSRF fallback, `tableFilter.html` `%` wildcard). | various | code |

Checked and OK (yuf): JWT (fixed RS256, `none`/HS256 rejected, signature before claims, exp/nbf/iat, aud/iss/tid
exact), JWKS fetch (fixed host, TLS verify, no redirects, size limit), Argon2id + rehash, `SecretTokenHash`, CSRF
(32 bytes, `hash_equals`, POST only, fails closed), CSP (nonce + `strict-dynamic`, `frame-ancestors 'none'`), session
(strict mode, cookie only, Secure/HttpOnly/SameSite=Strict, id regex, IP/UA binding, regenerate on login), template
engine (rejects `<?`, compiler emits only `var_export` literals, auto-escaping, cache outside web root), `FileCache`
(JSON, 0600), XML without XXE, DB (`EMULATE_PREPARES=false`, bound LIMIT, whitelisted ORDER BY, escaped LIKE), cURL
client (TLS verify, no redirects, header validation), mailer header/SMTP injection, `mail()` `-f` injection, phone
parser (no ReDoS), all form renderers and table cells encode, upload type check by finfo, `HtmlSanitizer`,
`IpValidator`, `LoginRedirect` (aside from Y-14), `checkDomain()`.

## Fix plan

Two repositories, so two commits: yuf first (release), then one big backend commit that raises yuf. Following the
standard ("Fix problems at their cause"), the yuf part is done in a yuf session with the prompt below; the backend adds
no workaround for yuf issues.

### Commit 1: actra/yuf → v6.0.0 (contains ⚠️ breaking changes, section 9 of `versioning.md`)

1. Y-1: validate `fileTitle` (`^[A-Za-z0-9_]+$`) and `fileGroup` (`^[A-Za-z0-9_/-]+$`, no `..` segment) centrally in
   `RequestHandler::resolveRoute()` → `NotFoundException`; in addition `realpath()` containment in
   `LocaleHandler`. Test with `/shop/${fileName}` and `../`.
2. Y-2: `MicrosoftIdToken::getObjectId()` (`oid`) and `getTenantId()`; `Authenticator` takes a user lookup by
   `oid`+`tid` (projects store it); keep `email` only with `xms_edov` check. ⚠️
3. Y-3: atomic attempt registration: `abstract protected function dbRegisterWrongPasswordAttempt(): int` →
   `UPDATE … SET wrong_login_attempts=wrong_login_attempts+1 WHERE id=? AND wrong_login_attempts<?`, check affected
   rows *before* `password_verify`, give it back on success. ⚠️ (new abstract method)
4. Y-4: SSO nonce must have ≥ 16 characters; better: yuf creates, stores and consumes the nonce itself.
5. Y-5: `spendVerificationTime()` also for legacy hashes; dummy hash for the active algorithm.
6. Y-6: keep the SSO nonce in its own short-lived `SameSite=None` cookie on the callback path, do not downgrade the
   session cookie (or regenerate the id on every callback).
7. Y-7: `AuthSession::logIn()` and impersonation clear the CSRF section.
8. Y-8: `InputField` with `maxLength` adds a `MaxLengthRule` (⚠️ default changes).
9. Y-9: `Form::validate()` checks CSRF first and returns before reading other fields; creating a POST form without
   token source throws unless explicitly opted out (⚠️).
10. Y-10: record issued pointers in the session section, ignore foreign ones; upload root becomes a required argument
    (no Host-derived default, ⚠️), `mkdir 0700`, refuse symlinks / foreign owner, files `chmod 0600`.
11. Y-11: refuse STARTTLS if `stream_get_meta_data()['unread_bytes'] > 0`.
12. Y-12: throw when `smtpUserName !== ''` without TLS (except loopback) (⚠️); add implicit TLS mode.
13. Y-13: check `allowedDomains` before the HTTPS redirect.
14. Y-14: `isLocalPath()` also requires `parse_url() !== false` without `host`/`scheme`; `FileLogger` hashes class,
    file, line and trace without the message.
15. Y-15: `FileHandler::output()` lets the content type decide; html/htm/xhtml/xml/svg/js download by default (⚠️);
    `Content-Security-Policy: default-src 'none'; sandbox` on file responses; RFC 6266 `filename`/`filename*`.
16. Y-16: reject control characters in mail addresses (and quoted local parts); `createSqlFilters()` validates keys
    with `FIELD_NAME_PATTERN`; `TableItem::renderValue()` → `HtmlEncoder::encode()`; column labels and navigation
    titles as `HtmlText` (⚠️ API); `ActionsColumn` rejects a target starting with a placeholder; re-check stored sort
    column; encode `PhoneNumberField::renderValue()`; spam token bound to the session; `singleLine()` for logged
    values; `form-action 'self'` in the default CSP; GC default 1/1000; `.env.example.php` `debug => false`; fix stale
    docs; document trusted-proxy limitation (`REMOTE_ADDR`) and `zend.exception_ignore_args=On`.

Tests for each item (PoCs in `audit-tmp/` can serve as templates), `README.md`/docs, complete `UPGRADE.md` entry.

### Commit 2: actra/backend → v2.7.0 (⚠️ breaking), requires the new yuf

1. B-2: remove the user (and its group assignment) from `db/data.sql`; document creating the first admin with an own
   address (SQL snippet in `docs/`); README: no "test user account". `UPGRADE.md`: check and delete
   `admin@actra.ch` in existing installations.
2. B-1: `DbAuthApiKeyRepository` gets `DbAuthUserRepository` and `hasApi`; `getUserIdForBearerOrThrow()` rejects
   unless the user is active with at least one right, has a non-empty IP whitelist containing `REMOTE_ADDR`, and the
   API is enabled. Delete API keys when a user is deactivated or loses all groups (`UserModForm`); allow removing keys
   when `hasApi` is false (`profileRemoveApiKey.php:56`, `userRemoveApiKey`). Decide: return `MyAuthUser` instead of
   the id (⚠️, lets projects check rights).
3. B-3, B-4, B-12: PASSWORD (and ACTIVATION) tokens become `SecretTokenHash::generate()` (32 bytes), stored as hash
   only; the 6-character code stays for LOGIN only, stored as HMAC/hash, session holds the hash, compared with
   `hash_equals`, attempts `>=`. Claiming is atomic (`UPDATE … SET claimed=? … WHERE id=? AND claimed IS NULL`,
   `rowCount() === 1`) and the reset claims before `setPassword()` in one transaction. `getClaimable()` also filters
   by user id. Token column removed from `TokenTable` and its search. Schema update `db/updates/2.7.0.sql`
   (`token` → `token_hash`, `utf8mb4_bin`; also `auth_api_key.public_id`). Per-IP limit on reset-link attempts.
4. B-5: `dbConfirmSuccessfulLogin()` sets `wrong_login_attempts=0`; with Y-3 implement the atomic registration.
   Decide: time-based lock (`locked_until`, growing delay) instead of permanent lock, and an admin "unlock" action.
5. B-6: send limit per requesting IP (and session) as primary limit; per-user limit higher, only against mail
   flooding, and it must not invalidate the code of a user who is mid-login; count + insert under a lock
   (`SELECT … FROM auth_user WHERE id=? FOR UPDATE` in a transaction).
6. B-7: check global list and user list separately (both must match when not empty); option to disable editing the
   own whitelist in the profile.
7. B-8: groups offered/accepted only if their rights are a subset of the actor's rights; no edit, impersonation,
   email change or deletion of users with rights the actor does not have; block deleting/deactivating oneself and
   the last active `manage_users` user; log impersonation in `auth_login`.
8. B-9: password change/reset/removal (and email change by an admin) delete the user's other `auth_session` rows and
   open tokens and regenerate the session id.
9. B-10, B-11: impersonation and leaving it become `ConfirmationView`s (POST + CSRF), logout by POST/CSRF;
   impersonated sessions re-check the impersonator (active + `manage_users`) on every request.
10. B-13: count wrong current passwords in the profile, log out at the limit.
11. B-14: absolute URLs from a required `ActraBackendSettings::$baseUrl` (⚠️ new required argument), never from
    `getHost()`; `profile.php` uses `addText`.
12. B-15: length rules matching the column sizes in user, profile and invite forms (also covered by Y-8).
13. B-16: navigation titles and column labels as `HtmlText` (after Y-16); render unparsable phone numbers as escaped
    raw value; timing-equal token forms.

Tests: the PoCs in `audit-tmp/auth/AuthPocTest.php` (API key, reset reuse, lockout, send-limit DoS, whitelist
merge) turned into regression tests in `tests/Unit/` (they must fail before and pass after the fix); new views in
`ViewRenderTest`. `composer check` green. `UPGRADE.md` v2.7.0 with ⚠️ entries for B-1, B-2, B-3/B-4 (schema, old
reset links invalid), B-8, B-10, B-14.

Frontend review: probably none. The impersonation link on `user.html` changes its URL (filled from PHP); the new
confirmation views use the existing confirmation template. Re-check when implementing.

## Decisions (owner, 2026-10-10)

They override the fix plan above where it differs.

1. Order: yuf first, as one big commit, released as **v6.0.0**; then the backend in one big commit.
2. Y-2: Microsoft SSO identifies users by `oid` + `tid` (no email identification).
3. Y-4/Y-6: yuf creates, stores and consumes the SSO nonce itself (own short-lived cookie); the session cookie stays
   `SameSite=Strict`.
4. Y-3/B-5: the lock-out stays **permanent** and the counter is **not** reset on successful login (only by setting a
   new password). Only the race is fixed (atomic counting). B-5 is therefore no bug; the "reset on success" fix is
   dropped, and yuf's `AuthUser::confirmSuccessfulLogin()` stops resetting the counter.
5. Changed defaults (Y-8, Y-9, Y-10, Y-12, Y-15): all on, no opt-out switches, except explicit opt-outs for a
   legitimate use (e.g. a form without session, SMTP AUTH on loopback).
6. B-14: absolute URLs keep using the Host header (multi-site projects serve several domains). It is safe because
   `RequestHandler::checkDomain()` enforces `allowedDomains`; Y-13 closes the one path before that check. No base URL
   setting. `profile.php` still switches to `addText`.
7. Y-16: raw-HTML string APIs (column labels, navigation titles, `ActionsColumn` targets) become typed `HtmlText`.
8. Spam token: bound to the session.
9. B-2: keep an active seed user, but with a reserved address that can never receive mail:
   `admin@example.invalid` (RFC 2606/6761: `.invalid` never resolves, unlike `admin@localhost`, which would deliver
   to the local mailbox of the server). Installers change it to their own address; docs and `UPGRADE.md` say so.
10. B-1: the API key check returns `MyAuthUser` (breaking, renamed method) and checks active, rights, IP whitelist
    and `hasApi`.
11. B-8: subset rule (only groups whose rights the editor has; no editing/impersonating/deleting users with more
    rights; not oneself or the last `manage_users` user).
12. B-6: keep the per-user send limit, only make count + insert atomic. No per-IP limit, no admin unlock action.
13. GitHub security advisories after the releases (texts drafted by the AI session, published by the owner).
14. Tags: keep lightweight tags for now.

## Prompt for the yuf session

Security release of actra/yuf as **v6.0.0**, all fixes in one commit. Source: the audit plan in the backend checkout,
`../backend/docs/plans/security-audit/plan.md` (sections "Findings: actra/yuf", "Commit 1" and "Decisions"; the
decisions win where they differ). Proof-of-concept scripts are in `../backend/audit-tmp/` (run in the backend's DDEV;
turn them into unit tests here). The audit file describes unfixed vulnerabilities: do not copy it into yuf and do not
mention exploit details in public docs beyond what users need.

```text
Fix the security findings Y-1 to Y-16 of the audit in ../backend/docs/plans/security-audit/plan.md in actra/yuf, as
one release v6.0.0. Read that plan (findings table "actra/yuf", "Commit 1", and "Decisions", which win) and the PoCs
in ../backend/audit-tmp/ first. Follow AGENTS.md and the coding standard; no compatibility layers. Each fix gets a unit
test that fails before the fix.

Decisions of the owner:
- Y-1 (path traversal into require of *.lang.php via route path variables, PoC core_poc2.php): validate fileTitle
  (^[A-Za-z0-9_]+$) and fileGroup (^[A-Za-z0-9_/-]+$, no ".." segment) centrally in RequestHandler::resolveRoute()
  -> NotFoundException; additionally realpath containment in LocaleHandler.
- Y-2: Microsoft SSO identifies users by oid + tid only, never by the email claim. MicrosoftIdToken exposes
  getObjectId()/getTenantId(); the Authenticator API looks users up by oid+tid (projects store it). Breaking.
- Y-4/Y-6 (PoCs jwt.php, sess/s.php): yuf creates, stores and consumes the SSO nonce itself in its own short-lived
  cookie (Secure, HttpOnly, SameSite=None, path of the callback, single use); remove the nonce from the project API;
  never downgrade the session cookie (drop changeCookieSameSiteToNone() if nothing else needs it); an empty/short
  nonce is impossible.
- Y-3/lock-out: the lock stays permanent and the wrong-password counter is NOT reset on successful login (only when a
  new password is set). Remove the reset from AuthUser::confirmSuccessfulLogin(). Make counting atomic: register the
  attempt in the DB before password_verify (new abstract method, e.g. dbRegisterWrongPasswordAttempt(): int with
  UPDATE ... SET wrong_login_attempts=wrong_login_attempts+1 WHERE id=? AND wrong_login_attempts<?, check affected
  rows), and give the attempt back on success, so parallel requests cannot exceed the limit.
- Y-5: spend the verification time also for legacy SHA-256 hashes; dummy hash for the active algorithm.
- Y-7: AuthSession::logIn() and impersonation clear the CSRF section (new token lazily).
- Changed defaults, all on, no opt-out switches (only explicit opt-outs for a legitimate use):
  - Y-8: InputField with maxLength adds a MaxLengthRule (PoC form_poc.php).
  - Y-9: Form::validate() checks CSRF first and returns before reading other fields (no upload storage, no
    _removeAttachment, no listeners on an invalid token); a POST form without token source throws unless the caller
    explicitly opts out (e.g. csrf: false).
  - Y-10 (PoC upload_poc.php): upload pointers are recorded in the session section and foreign pointers are ignored
    (new pointer); the upload root is a required argument (no sys_get_temp_dir()/SERVER_NAME default), created 0700,
    symlinks or foreign owners refused, stored files 0600.
  - Y-12: SMTP AUTH without TLS throws, except on loopback; add an implicit TLS mode (port 465).
  - Y-15: FileHandler::output() lets the content type decide; html/htm/xhtml/shtml/xml/svg/js are downloaded by
    default; file responses get "Content-Security-Policy: default-src 'none'; sandbox"; Content-Disposition with
    RFC 6266 filename (escaped ASCII) and filename*.
- Y-11 (PoC dbagent_starttls_poc.php): refuse STARTTLS when stream_get_meta_data()['unread_bytes'] > 0.
- Y-13: absolute URLs keep using the Host header (multi-site), but the HTTP->HTTPS redirect in Core must happen only
  after the host was checked against allowedDomains (unknown host: 404, no redirect). Document that getHost() is only
  trustworthy after checkDomain().
- Y-14 (PoC core_poc1.php): LoginRedirect::isLocalPath() additionally requires parse_url() !== false without scheme
  and host; FileLogger builds the issue hash from class, file, line and trace, not from the message.
- Y-16:
  - raw-HTML string APIs become typed HtmlText: table column labels (TableHeadRenderer, SortableTableHeadRenderer),
    NavigationItem titles; ActionsColumn link targets reject a target that starts with a placeholder (javascript:).
  - spam token (SpamCheck) bound to the session (HMAC includes a session-bound value; PoC spam_poc.php).
  - MailerAddress and the email field reject control characters and quoted local parts.
  - SearchQueryBuilder::createSqlFilters() validates its keys (FIELD_NAME_PATTERN).
  - TableItem::renderValue() uses HtmlEncoder::encode(); PhoneNumberField::renderValue() is encoded.
  - DbResultTable accepts a stored sort column only if it is sortable in this table.
  - RequestLogFormatter puts logged query/post values and messages on one line.
  - default CSP gets form-action 'self'.
  - session GC default 1/1000; FileSessionHandler respects "N;" in save_path; CachedKeySet throttles fetches also
    without cache file.
  - .env.example.php ships debug => false.
  - fix stale docs: FormInput.php:44 (no CSRF fallback in the query), tableFilter.html legend (% is no wildcard).
  - docs: client IP is REMOTE_ADDR only (behind a reverse proxy configure the server, e.g. mod_remoteip);
    recommend zend.exception_ignore_args=On; SSO oid mapping and nonce handling; upload root.

Release: version v6.0.0. UPGRADE.md gets a complete v6.0.0 section: every changed default, new required argument,
new abstract method and changed signature as ⚠️ with what to do; the security fixes listed without exploit details.
Update README.md and docs/. composer check must be green, the PHPStan baseline may only shrink. Do not commit;
show git diff --stat and propose the commit message (Conventional Commits, e.g.
"fix(security)!: ..." with a short body listing the areas). Also return a short list of what actra/backend must
migrate (changed APIs it uses), so the backend session can raise yuf to ~6.0.0.
```

## Limits of this audit

- The ~150 feature commits of yuf in October were covered by reviewing the current security-relevant code and
  history heuristics, not line by line.
- No live tests against Microsoft Entra, a real SMTP server or production; no load test of the race conditions
  (B-6, Y-3) – they are derived from the code.
- Project code of consumers (e.g. the Host-based redirect in `../drogeriehaas.ch/public/index.php`) is out of scope.

## Handover notes

- 2026-10-10: audit done by an AI session (7 parallel reviewers + verification). PoC scripts are in the git-ignored
  `audit-tmp/` of this checkout (also reachable via the DDEV web server – delete the folder when no longer needed).
  Nothing in `src/` was changed.
- 2026-10-11: yuf fixes released as v6.0.0 (v6.0.1: table filter help text only). Backend fixes done for v2.7.0 in one
  commit, following the decisions above, with these deviations and additions:
  - Password reset (and activation) links have 22 letters and digits (about 131 bits) instead of 32 random bytes: long
    links were broken across lines in text mails before. Login codes keep 6 characters (bound to the session). Both
    are stored as SHA-256 hash only (`token_hash`, `db/updates/2.7.0.sql`, checked: old schema plus script equals the
    new schema except the order of two indexes).
  - B-4: no per-IP limit on reset links (decision 12); the longer links make guessing impossible.
  - B-10: logout stays a GET link. The login pages log out on GET by design, and `SameSite=Strict` blocks cross-site
    requests (yuf v6 no longer lowers it); a POST logout would need a template change.
  - B-1: removing an API key while `hasApi` is false is still not possible (the link is hidden by the templates), but
    such keys are refused, so nothing is exposed.
  - B-8: the "last active user with `manage_users`" rule has no test of its own (the shared test database always has
    other administrators); self-deletion and the subset rule are tested.
  - B-16: the small timing difference between known and unknown email addresses in the token forms is not changed.
  - Regression tests: `ApiKeyAuthenticationTest`, `DbAuthTokenRepositoryTest`, `PasswordResetFormTest`,
    `AccessControlTest` (IP whitelists, subset rule, impersonation by POST, end of impersonation), adapted
    `AuthTokensTest`. Test doubles: `RecordingMailer` (the login code comes from the mail, the session keeps only its
    hash), CSRF token source in `ViewContextFactory`, IP whitelist and mailer arguments of the test instances.
  - Frontend review (no template, CSS or JavaScript was changed):
    - `user.html`: the buttons "Edit user", "Delete user", "Send welcome email" and the API key links are shown also
      for users the current user may not manage (the pages answer 404). Task: show them only if the view provides a
      link (the view can pass empty hrefs, as for `impersonateHref`).
    - `user.html` ("Impersonate user") and `templates/default.html` ("Cancel session change"): the links now open
      confirmation pages (`userImpersonate-{ID}.html`, `userImpersonateEnd.html`). Task: decide whether they use the
      confirmation dialog (`data-action="confirm-deletion"` with `data-confirm` texts) like the delete links.
    - Token log (`tokens.html`): the column "Token" is removed; check the column widths.
    - Input fields of names, email address (user, profile) and notification subject get `maxlength="200"`.
  - The PoC folder `audit-tmp/` (git-ignored, also reachable through the DDEV web server of this checkout) can be
    deleted.
  - Advisories (owner publishes after the releases): drafts in the final report of the session.
- 2026-10-11 (v2.7.1): the owner decided that `manage_users` is the right of full control (no delegated user managers
  in any project), so the subset rule of B-8 is removed; the self and last-manager rules stay. Impersonation and its
  end act with one click again: instead of a confirmation page, the links carry the CSRF token of the session
  (`BackendView::createCsrfLink()` / `hasValidCsrfLinkToken()`). No GitHub advisories (the packages are used almost
  only by Actra projects); the installations are upgraded directly. Frontend review: the impersonation links no longer
  need a confirmation dialog (task of v2.7.0 obsolete), and the remark about hidden buttons for users with more rights
  is obsolete.
