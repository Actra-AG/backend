# Upgrade Guide

Changes of `actra/backend`, newest first. ⚠️ marks breaking changes. Older versions: [v1](docs/upgrade/v1.md).

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
