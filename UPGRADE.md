# Upgrade Guide

Changes of `actra/backend`, newest first. ⚠️ marks breaking changes. Older versions: [v1](docs/upgrade/v1.md).

## v2.7.2 (2026-10-11)

- Requires `actra/yuf` `~6.1.0` (no breaking change; projects can read the SMTP encryption with
  `EnvironmentSettings::getEnum()`, see yuf's `UPGRADE.md` v6.1.0). No change for projects.

## v2.7.1 (2026-10-11)

### Changes

- `manage_users` gives full control again, as before v2.7.0: a user with it manages all users and grants every group.
  The rules of v2.7.0 that stay: nobody deactivates or deletes their own account, the last active user with
  `manage_users` keeps it ([docs/users.md](docs/users.md)).
- "Impersonate user" and "Cancel session change" act with one click again, without confirmation page; the links carry
  the CSRF token of the session. `UserMessages::$impersonateConfirm` and `LayoutMessages::$cancelSessionChangeConfirm`
  (new in v2.7.0) are removed.

## v2.7.0 (2026-10-11)

Security release. Read every ⚠️ entry; the fixes without action are listed at the end.

### ⚠️ Requires `actra/yuf` `~6.0.0`

Migrate your own code with yuf's `UPGRADE.md` (v6.0.0). For project code in the backend: column labels and navigation
titles are `HtmlText` (also `AbstractTable::createDateColumn(label:)`), and POST forms are checked for the CSRF token
first (tests that post a form send `csrftoken`).

```php
// Before
$this->createDateColumn(identifier: 'created', label: $messages->createdLabel, withTime: true);
// After
$label = HtmlText::fromText(text: $messages->createdLabel);
$this->createDateColumn(identifier: 'created', label: $label, withTime: true);
```

### ⚠️ Database: `db/updates/2.7.0.sql`

One-time tokens are stored as hash only (`auth_token.token` is `token_hash`), API key public IDs are compared
case-sensitively. Password reset links mailed before the update stop working (new links have 22 characters); login
codes requested before the update must be requested again.

### ⚠️ Seed user of `db/data.sql`

Earlier versions of `data.sql` created the active administrator `admin@actra.ch`. Give that user your own address or
delete it in every installation: `SELECT id, email, active FROM auth_user WHERE email='admin@actra.ch'`. New
installations get `admin@example.invalid`, which cannot receive mail: change it to your own address (README).

### ⚠️ API keys: `authenticateBearerOrThrow()`

`DbAuthApiKeyRepository::getUserIdForBearerOrThrow()` is removed. `ActraBackend::authenticateBearerOrThrow()` returns
the user and refuses keys of inactive users, users without rights, requests from outside the user's IP whitelist and
all keys while `hasApi` is false. Check the rights of the endpoint with the user.

```php
// Before
$userId = $actraBackend->getRepositories()->apiKeys()->getUserIdForBearerOrThrow(httpRequest: $httpRequest);
// After
$myAuthUser = $actraBackend->authenticateBearerOrThrow(httpRequest: $httpRequest);
$userId = $myAuthUser->id;
```

### ⚠️ Users who manage users, impersonation, IP whitelists

- A user with `manage_users` manages only users who have no right that this user lacks, and grants only such groups;
  nobody can deactivate or delete their own account, and the last active user with `manage_users` keeps it
  ([docs/users.md](docs/users.md)). Check that your administrators have all project rights they must grant.
- Impersonation and "Cancel session change" are confirmation pages (POST with CSRF token): `userImpersonate-{ID}.html`
  and `userImpersonateEnd.html`. `user::PARAM_IMPERSONATE` and `BackendView::PARAM_CANCEL_SESSION_CHANGE`
  (`?impersonate`, `?cancelSessionChange`) are removed.
- The IP whitelist of a user no longer extends the whitelist of the settings: after the login both apply.

### ⚠️ Changed APIs of the token repository

`DbAuthTokenRepository::claim()` returns `bool` (`false`: claimed before, do not use the token), `getClaimable()` has
the new argument `userId:`, new are `createTokenWithinLimit()` and `deleteUnclaimedByUserId()`.
`LogMessages::$tokenColumn` is removed (the token log has no token column). `DbAuthGroup` has the new property
`accessRightCollection`.

### Security fixes without action

- A password reset link sets the password only once, ends all sessions and open tokens of the user; reset links have
  about 131 random bits. Changing or removing the password in the profile ends the other sessions.
- Login codes are kept as hash in the session and the database and compared in constant time; a code allows
  `maxAllowedLoginAttempts` tries (one more before).
- Wrong passwords are counted atomically (also the current password in the profile); the send limit of tokens is
  counted under a lock, so parallel requests do not exceed it.
- An impersonation ends when the impersonating user may no longer manage the impersonated user.
- Deactivating a user ends the sessions and deletes the open tokens and the API key.
- User, profile and notification forms check the length of names, email address and subject on the server.
- The profile escapes the login link; a stored phone number that cannot be parsed no longer breaks the user page.

## v2.6.0 (2026-10-10)

### New

- Send limit for login codes and password reset links: at most 5 per user and type within 15 minutes, on by default.
  Above the limit, the forms answer as before but send nothing. Change it with
  `ActraBackendSettings(tokenSendLimit: new TokenSendLimit(maxTokens:, withinMinutes:))`, turn it off with
  `tokenSendLimit: null` ([docs/users.md](docs/users.md)).

## v2.5.1 (2026-10-10)

### Changes

- Requires `actra/yuf` `^5.5.0`. Projects that create a `ViewContext` themselves (test doubles) pass `documentRoot:`
  (see yuf's `UPGRADE.md`, v5.5.0); nothing else to migrate.

## v2.5.0 (2026-10-10)

### New

- Project services in project views: `ActraBackend::createViewFactory(create:)` takes a closure
  `fn(string $className, BackendViewContext $context): BackendView` that creates the project views based on
  `BackendView` with further constructor arguments (repositories, mailers). Without closure nothing changes; the views
  of the backend ignore it ([docs/views.md](docs/views.md), "Project services").
- `ConfirmationView`: base class of a confirmation page for a destructive action of a project, with the template and
  look of the confirmation pages of the backend. GET shows the page, only a valid POST with CSRF token runs `confirm()`
  and redirects. Works with the dialog (`data-action="confirm-deletion" data-form="main form"`). Replace actions on a
  plain GET link and own confirmation views with it ([docs/views.md](docs/views.md), "Confirmation page of a
  project").

### Changes

- The confirmation pages `userDelete`, `userRemoveApiKey`, `userGenerateApiKey`, `profileRemoveApiKey` and
  `profileGenerateApiKey` are based on `ConfirmationView`; the look stays the same. Only attributes change: the form
  action is `?ConfirmationForm` (was `?UserDeleteForm`, `?ApiKeyRemoveForm`, `?ApiKeyGenerateForm`) and the button
  name `confirm` (was `delete`, `remove`, `generate`). Projects that post these forms directly (tests, scripts) adapt
  the names; links with `data-form="main form"` keep working.
- Requires `actra/yuf` `^5.4.0` (`ConfirmationView` uses `HtmlDocument::useContentFile()`, so it also works on routes
  with file groups; nothing to migrate in projects) and `actra/coding-standard` `^1.23.0` (development only).

## v2.4.4 (2026-10-10)

The tag `v2.4.3` points to an old commit of v1 (2026-10-04) by mistake; do not use it. These fixes were released as
v2.4.4.

### Fixes

- Login pages look as in v2.3.1 again: the hidden `returnTo` field comes first in the forms of `login.html`,
  `loginPassword.html`, `loginToken.html` and `loginPasswordToken.html`, so the button row gets its margin.
- The user list and the notification list show the last name instead of `[lastName]`.
- `passwordResetRes.html` no longer fails ("loginText does not exist").
- Requires `actra/yuf` `~5.2.1`: the search filters of `users.html`, `tokens.html` and `visits.html` can be reset to
  "all" again.
- `user-<id>.html`: `<html lang>` is the language of the page again (was the language of the user); the user's
  language is the replacement `userLanguage` in `user.html`.

## v2.4.2 (2026-10-10)

### Fixes

- Requires `actra/yuf` `~5.2.0`: the message of a notification is rendered with `HtmlText::fromTextWithLineBreaks()`;
  its line breaks are `<br>` instead of `<br />`, the look stays the same.

## v2.4.1 (2026-10-10)

### Fixes

- Requires `actra/yuf` `~5.1.1`: the search fields are rendered as in v2.3.1, `<div><label for="…">…</label>…</div>`
  without `class="form-compact-field"`; only the corrected `for` differs.
- Project search forms based on `AbstractSearchForm` keep their renderer as in v2.3.1 (v2.4.0 turned on the compact
  renderer for them, `<div>` instead of `<dl>`). A form that wants it calls `$this->useCompactFieldRenderer()`.
- Requires `actra/coding-standard` `^1.22.0` (development only).

## v2.4.0 (2026-10-10)

### ⚠️ Requires `actra/yuf` `~5.1.0`: Composer loads all classes

Follow yuf's `UPGRADE.md` v5.1.0. Before: `require …/vendor/actra/yuf/src/Core.php;` and
`Core::fromEnvironment(…, autoloaderPath: …)`. After: `require __DIR__ . '/../vendor/autoload.php';` first in
`public/index.php` and every CLI script, `Core::fromEnvironment()` without `autoloaderPath:`, the project namespace
in `composer.json` (`"autoload": {"psr-4": {"app\\": "app/"}}`), deployment with
`composer install --no-dev --optimize-autoloader`. Without it: `Class "actra\backend\settings\ActraBackendSettings" not
found`.

### ⚠️ Search forms with yuf's compact renderer

`SearchSelectOptionsField` is removed (`SelectOptionsField`), `SearchQueryField` has no own HTML: the backend's search
forms use yuf's `CompactFieldRenderer`. The markup stays the same apart from the corrected `for` of the labels (the id
of the control instead of the field name). v2.4.0 added `class="form-compact-field"` to the `<div>`; v2.4.1 removes it
again.

### ⚠️ Login forms carry the requested page

The forms of `login.html`, `loginPassword.html`, `loginToken.html` and `loginPasswordToken.html` have a hidden field
`<input type="hidden" name="returnTo" …>`. `MyAuthUser::getFirstAllowedPage()` and `redirectToFirstAllowedPage()`
take `returnPath:` (before: read from the request).

### ⚠️ Date columns from yuf

`LocalizedDateColumn` is removed. Before: `new LocalizedDateColumn(identifier: …, label: …, dateFormatter: …,
withTime: true)`. After: `$this->createDateColumn(identifier: …, label: …, withTime: true)` in a table based on
`AbstractTable`, or yuf's `DateColumn` with `useLocale()`. The output is the same.

### Fixes

- No 401 after a successful login with a code (password + code, or code only).
- The requested page (`returnTo`) is kept through all login steps.
- Navigation order as in v2.2: the project items before the users item.
- German: "Ihr" in the subject of the login code mail and in the password reset text.
- Redirects use the `ResponseSender` of the request.

## v2.3.1 (2026-10-10)

### Other changes

- `DbAuthUserCollection::getFormOptions()` adds the user ids with `addIntItem()`: read them with `getValueAsInt()` /
  `getIntValues()`.
- Requires `actra/coding-standard` `^1.19.0` (development only).

## v2.3.0 (2026-10-10)

### ⚠️ Requires `actra/yuf` `~5.0.0`

Raise yuf to `^5.0.0` and follow its `UPGRADE.md` v5.0.0 (e.g. remove `scanDirectories` for yuf from `phpstan.neon` and
the yuf path from the test bootstrap). The backend no longer registers itself with `actra/autoloader`: it is loaded
through Composer's autoloader.

### ⚠️ Navigation per request

Before: `ActraBackend::init(…, navigationItemCollection: $navigationItemCollection)`. After: no such argument; pass the
backend navigation to yuf:

```php
$core->prepareHttpResponse(routeCollection: $routeCollection, navigationProvider: $actraBackend->createNavigation(...));
```

`ActraBackend::$navigationItemCollection` is removed: `$this->backendContext->getNavigation()`. Project items still
come from `ActraBackendSettings::$projectNavigation`.

### ⚠️ Login redirect with `loginPath:`

Before: the backend redirected to its login page itself. After: `new RouteCollection(loginPath: '/backend/login.html')`;
after the login the user gets back to the requested page (`?returnTo=`). Without a login path, a page that needs a
login answers with 401. `BackendView::PARAM_FROM_LOGIN` and `MyAuthUser::setRequestedPageAfterLogin()` are removed.

### ⚠️ Tables and search forms

- `AbstractTable::export(name:)` and its `clock:` argument are removed: `$table->exportCsv(fileName: 'users.csv',
  responseSender: $this->context->responseSender)`.
- `AbstractSearchForm::validateSearchField()` is split into `validateTextSearchField()`, `validateOptionsSearchField()`
  (string keys) and `validateIntOptionsSearchField()` (integer keys, `null` for all). Option keys have no `option_`
  prefix.
- `DbAuthGroupCollection::getFormOptions()` has integer keys (`addIntItem()`); `listFormOptionKeys()` is removed
  (`getFormOptions()->getKeys()`). `DB::selectRowsFromQuery()` is removed (`$dbQuery->selectRowsFromDb()`).
- The visit status filter lists the login results only: the options "No access" (6) and "Unconfirmed access" (9) and
  their texts `LogMessages::$filterNoAccess` / `$filterUnconfirmedAccess` are removed.

### ⚠️ Texts of yuf

`CommonMessages::$tableNoEntries`, `$tableOneResult` and `$tableResults` are removed: `BackendMessages::$table`
(yuf's `TableMessages`, placeholder `[amount]`). `LogMessages::authResult()` and its `$authResult…` texts are removed:
`BackendMessages::$authResult` (`AuthResultMessages`), e.g. `$authResult->label(messages:
$messages->authResult)`. English wording: "Access inactive" instead of "Account inactive".

### ⚠️ Mailer and records

- `ActraBackend::createMailer()` requires `responseSender:` (`new NativeResponseSender()` in CLI scripts).
- `DbAuthUser` takes `ipWhitelist:` (`list<string>`) instead of `rawIpWhitelist:` and no longer adds yuf's removed
  `ACCESS_DO_PASSWORD_LOGIN` right. Users with a password but without any right are now rejected as inactive.

### Other changes

- Token requests of users with too many wrong passwords are rejected (`ERROR_OUT_TRIED`).
- New passwords are checked by the form (`PasswordField::setMinLength()`, `EqualsFieldRule`): both errors may show at
  once.

## v2.2.0 (2026-10-09)

### ⚠️ `MailerSettings` takes the mailer of the project

Microsoft 365 works with `GraphMailer` (no SMTP basic auth). Before: `new MailerSettings(senderEmail: …,
senderName: …, hostname: …, username: …, password: …, port: …, tls: …, signature: …, serverNameCache: …)`. After:
`new MailerSettings(senderEmail: …, senderName: …, signature: …, mailer: new SmtpMailer(…))` or `GraphMailer`
([README.md](README.md#basic-initialization)). `ActraBackend::createMailer()` has no argument.

### ⚠️ Dates in the format of the locale

Dates are formatted with `IntlDateFormatter` for `Language::$locale` of the route (`09.10.2026, 14:05:33` for `de_CH`,
`9 Oct 2026, 14:05:33` for `en_GB`). `CommonMessages::$dateFormat` and `$dateTimeFormat` are removed;
`DbAuthUser::renderLastLogin()` and `DbAuthUserNotification::render()` take a `DateFormatter`
(`$backendContext->route->dateFormatter`).

### Other changes

- Login codes and password reset links are mailed after the response, so the response time does not tell whether an
  email address exists. A failing mail server is logged, the user no longer sees an error.

## v2.1.0 (2026-10-09)

### ⚠️ Requires `actra/yuf` `~4.67.3`

Read yuf's `UPGRADE.md` from v4.58.0 to v4.67.3: generated pages are no longer stored by browsers, templates are not
checked for changes without `debug` (delete `app/cache/v*/` on every deployment), and the mailers and `FileLogger` of
the project need new arguments. yuf now requires `actra/autoloader` `~1.2.0`.

### ⚠️ `MailerSettings` requires `serverNameCache:`

Before: `new MailerSettings(…, signature: '…')`. After: add `serverNameCache: $core->fileCache` (the host name of the
server is looked up once a day), or `serverNameCache: null`.

### Other changes

- The documentation moved from `README.md` to `docs/` and is part of the package; the upgrade notes of v1 are in
  `docs/upgrade/v1.md`.

## v2.0.0 (2026-10-09)

### ⚠️ Database in snake_case: `db/updates/2.0.0.sql`

All tables, columns and indexes of the backend follow the coding standard (`naming.md`): `auth_ipWhitelist` is
`auth_ip_whitelist`, `ID` is `id`, `userID` is `user_id`, `firstName` is `first_name`, `passwordHash` is
`password_hash`, and so on. The update script renames them and keeps all data, indexes and foreign keys (checked:
the old schema plus the script equals the new `db/schema.sql`). It needs MariaDB ≥ 10.5 or MySQL ≥ 8.0
(`RENAME COLUMN`).

```sql
-- Before
SELECT auth_user.ID, auth_user.firstName
FROM auth_user
         INNER JOIN auth_ipWhitelist ON auth_ipWhitelist.userID = auth_user.ID;

-- After
SELECT auth_user.id, auth_user.first_name
FROM auth_user
         INNER JOIN auth_ip_whitelist ON auth_ip_whitelist.user_id = auth_user.id;
```

The columns of the tables of the backend have the new names, so their sort parameters in links change (e.g.
`?…|first_name|asc`).

### ⚠️ Acronyms like normal words

Every name of the backend writes `ID` as `Id` (or `id` at the start): properties, parameters, named arguments and
methods.

```php
// Before
$dbAuthUser->ID;
$repositories->users()->selectByID(ID: 5);
$paths->user(ID: 5);
$userController->deleteUser(userID: 5);
$apiKeys->getUserIDForBearerOrThrow(httpRequest: $httpRequest);
public function beforeDeleteUser(int $userID): void // UserDeleteHandler

// After
$dbAuthUser->id;
$repositories->users()->selectById(id: 5);
$paths->user(id: 5);
$userController->deleteUser(userId: 5);
$apiKeys->getUserIdForBearerOrThrow(httpRequest: $httpRequest);
public function beforeDeleteUser(int $userId): void // the backend calls it with userId:
```

Search your project for: `auth_` (own SQL on the backend tables), `->ID`, `ID:`, `userID`, `ByID(`, `IDs(`,
`ForBearerOrThrow(`, `beforeDeleteUser(`.
