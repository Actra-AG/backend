# Upgrade Guide

This document tracks relevant changes for both frontend and backend developers, newest first. ⚠️ marks breaking
changes.

## v1.10.0 (2026-10-08)

### Breadcrumb with declared parents

A view based on `BackendView` can declare its parent pages with `getBreadcrumbParents()` (`BreadcrumbItemCollection`
of `BreadcrumbItem`, plain text title and link). They replace the trail of the visited pages, which showed stale
entries after a direct jump between detail pages (bookmark, link in an email). Views without parents keep the trail
(`useNavigator: true`); no change needed. See README "Breadcrumb".

```php
protected function getBreadcrumbParents(): ?BreadcrumbItemCollection
{
    return new BreadcrumbItemCollection(
        new BreadcrumbItem(title: $this->event->title, href: event::getPath(ID: $this->event->ID)),
    );
}
```

### Session data through yuf's `Session`

The backend no longer reads or writes `$_SESSION` or `$_GET`: the breadcrumb trail, the active navigation levels
(`?n=`), the login and password tokens, the generated API key and the page after login use the `Session` of the
request (same keys, so running sessions keep their data). Needed for yuf v4.46 (the session is started on first use).
The link of a breadcrumb entry is escaped now (it was output as it came from the URL).

### ⚠️ Removed and changed internal classes

- `OldNavigator` is removed; its behaviour is in the new internal `SessionBreadcrumbTrail`.
- `GeneratedApiKeyFlash::store()` / `pull()` and `AuthTokenTypeEnum::createAndSend()` / `claim()` take the `Session`
  as first argument (`session: ActraBackend::get()->getSession()`). `claim()` looks up tokens of its own type (it
  always used `LOGIN`; only `LOGIN` called it).
- New: `ActraBackend::getSession()`.

Search your project for: `OldNavigator`, `GeneratedApiKeyFlash::`, `->createAndSend(`, `->claim(`, `$_SESSION['sess_`.

## v1.9.0 (2026-10-08)

### ⚠️ Requires `actra/yuf` `^4.34`

Was `^4.15`. One step over yuf v4.16–v4.34: yuf v4.18.0–v4.31.0 ship `FrameworkDb`, `CsvFile`, `SmtpMailer` and
`SimpleXmlExtended` in files with the old case (`FrameworkDB.php`, …), which `actra/autoloader` cannot load on a
case-sensitive file system; v4.32.0 fixed them. Migrate the own code of the project with yuf's `UPGRADE.md`
(v4.16.0–v4.34.0): request and session are objects (`$this->context->httpRequest`, `->session`, `->authSession`),
forms need `context: $this->context->formContext`, the new template engine, `fromText()` / `fromHtml()`, `DbSettings`
without `identifier`.

Effects on running installations:

- Every user is logged out once: yuf keeps its session data under `$_SESSION['yuf']` and the auth session under the
  key `authSessionId`.
- Delete the compiled templates in the cache directory of the project (new template engine).
- CSV exports prefix cells starting with `=`, `+`, `-`, `@`, tab or carriage return with `'` (formula protection),
  also phone numbers like `+41 …`.
- Security: the group names of a user, the IP addresses of a whitelist and the JavaScript and CSS paths of the page
  templates are escaped (they were output unescaped).

### ⚠️ Names from yuf

```php
// Before
MyAuthUser::get()->ID;
$myAuthenticator->logAuthResult(userID: $id, sessionID: $sessionId, ip: $ip, userName: $name, authResult: $result);
AuthResult::ERROR_WRONG_PASSWORD;

// After
MyAuthUser::get()->id;
$myAuthenticator->logAuthResult(userId: $id, sessionId: $sessionId, ip: $ip, userName: $name, authResult: $result);
AuthResultEnum::ERROR_WRONG_PASSWORD; // also in LogMessages::authResult()
```

### ⚠️ Forms of the backend get the `BackendViewContext`

Like the search forms in v1.8.0, the forms of the backend (`LoginForm`, `LoginTokenForm`, `UserModForm`, …) take the
context as first argument:

```php
// Before
new LoginTokenForm();

// After
new LoginTokenForm(context: $this->backendContext);
```

### ⚠️ `getUserIDForBearerOrThrow()` needs the request

API views are not based on `BackendView`, so the request is passed:

```php
// Before
$userID = DbAuthApiKeyRepository::getUserIDForBearerOrThrow();

// After
$userID = DbAuthApiKeyRepository::getUserIDForBearerOrThrow(httpRequest: $this->context->httpRequest);
```

### ⚠️ `DbSettings` without `identifier`

`ActraBackend::init(dbSettings:)` and `DB::useConnection(dbSettings:)` take yuf's `DbSettings`, which has no
`identifier` any more; `DB` connects with `DbConnectionParameters::forMysql()`.

```php
// Before
new DbSettings(identifier: 'backend', hostName: 'db', databaseName: 'db', userName: 'db', password: 'db');

// After
new DbSettings(hostName: 'db', databaseName: 'db', userName: 'db', password: 'db');
```

### ⚠️ Search forms read the posted values, tables and search forms need a session

`AbstractSearchForm` reads its field values from the posted form only (before: also from the query string); `reset`
and `find` still come from the query string. Tables and search forms throw a `LogicException` on a route without
session.

### Request of a backend view

`BackendView` makes the `ViewContext` of its request current: `ActraBackend::get()->getViewContext()`,
`findViewContext()` (`null` outside a backend request, e.g. in a CLI script) and `getAuthSession()`. The static helpers
(`MyAuthUser::get()`, `Mailer`, the repositories and emails) use it, so their signatures stay the same. `Mailer` names
the server to the SMTP server with the address of the request, in a CLI script with `gethostname()`.

Search your project for: `->ID` (on `MyAuthUser`), `logAuthResult(`, `AuthResult`, `AuthMethod`, `identifier:` (in
`DbSettings`), `getUserIDForBearerOrThrow(`, `new LoginTokenForm(` and the other backend forms, `FrameworkDB`,
`CSVFile`, `SMTPMailer`, `HttpRequest::`, `AuthSession::`, `AbstractSessionHandler::getSessionHandler(`,
`HtmlText::unencoded(`, `HtmlText::encoded(`, `addEncodedText(`, `addUnencodedText(`, `addTextElement(`,
`isEncodedForRendering`, `new InputParameter(`, `redirectAndExit(`, `pushDownloadAndExit(`, `lastInsertId(`,
`new Form(`, `SearchHelper::getInstance(`.

## v1.8.1 (2026-10-08)

### README links the yuf setup of static analysis and tests

Documentation only: the README, section "Integration Tests", links yuf's README, section "Static analysis and tests"
(PHPStan `scanDirectories`, PHPUnit bootstrap with `actra/autoloader`). No code change needed.

## v1.8.0 (2026-10-08)

### ⚠️ Requires `actra/yuf` `^4.15`

Was `^4.14`. yuf 4.15.0 passes a `ViewContext` to every view and removes `HtmlDocument::get()` and
`JsonRequestBody::get()` (see yuf's `UPGRADE.md`, v4.15.0).

### ⚠️ Views based on `BackendView` get a `BackendViewContext`

`BackendViewContext` holds the yuf `ViewContext` and the services of the backend. It is the only constructor change
for project views in the migration to the current yuf: later releases add services to the context, not new arguments.
Routes with `BackendView` views use the view factory of the backend, which creates these views with the context
(other views on the route get the yuf `ViewContext` as before):

```php
// Before
new Route(path: '/de/orders/', viewDirectory: …, viewGroup: 'orders');

final class orders extends BackendView
{
    public function __construct()
    {
        parent::__construct(requiredViewGroupName: 'orders');
    }
}

// After
new Route(path: '/de/orders/', viewDirectory: …, viewGroup: 'orders',
    viewFactory: ActraBackend::get()->createViewFactory());

final class orders extends BackendView
{
    public function __construct(BackendViewContext $context)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'orders');
    }
}
```

Inside the view, `$this->context` is the yuf `ViewContext` (`route`, `pathVars`, `content`, `getHtmlDocument()`)
and `$this->backendContext` the `BackendViewContext`. Views without own constructor need no change.

### ⚠️ `AbstractTable` and `AbstractSearchForm` get the context

Both take the `BackendViewContext` as first argument; the `db` of a table is optional now (default `DB::get()`) and
comes after `itemsPerPage`:

```php
// Before
parent::__construct(identifier: 'OrderTable', db: DB::get(), dbQuery: $dbQuery, itemsPerPage: 50);
parent::__construct(name: 'OrderSearch'); // AbstractSearchForm
new OrderTable();

// After
parent::__construct(context: $context, identifier: 'OrderTable', dbQuery: $dbQuery, itemsPerPage: 50);
parent::__construct(context: $context, name: 'OrderSearch');
new OrderTable(context: $this->backendContext);
```

The tables and search forms of the backend (`UserTable`, `TokenTable`, `VisitTable`, `NotificationTable`,
`NotificationRecipientTable`, `UserSearchForm`, `TokenSearchForm`, `VisitSearchForm`) take the context the same way.

Search your project for: `extends BackendView`, `extends AbstractTable`, `extends AbstractSearchForm`, `new Route(`
(routes with backend views), `HtmlDocument::get()`, `JsonRequestBody::get()`, `RequestHandler::get()->route`,
`RequestHandler::get()->pathVars`, `ContentHandler::get()`.

## v1.7.0 (2026-10-08)

### ⚠️ Requires `actra/yuf` `^4.14`

Was `^4.10`. Read yuf's `UPGRADE.md` v4.10.1–v4.14.0. yuf 4.10.1 reads the session ID only from the cookie (no GET or
POST fallback).

### ⚠️ `DbSettings` instead of `DbSettingsModel`

yuf 4.12.0 renamed `DbSettingsModel` to `DbSettings` (coding standard v1.2.0). The backend follows with its argument
and property names:

```php
// Before
use actra\yuf\db\DbSettingsModel;
ActraBackend::init(…, dbSettingsModel: new DbSettingsModel(…), …);
ActraBackend::get()->dbSettingsModel;
DB::useConnection(dbSettingsModel: $dbSettingsModel);

// After
use actra\yuf\db\DbSettings;
ActraBackend::init(…, dbSettings: new DbSettings(…), …);
ActraBackend::get()->dbSettings;
DB::useConnection(dbSettings: $dbSettings);
```

### ⚠️ `TableItem` in table callbacks

yuf 4.12.0 renamed `TableItemModel` to `TableItem`. Callbacks of project tables (e.g. in classes extending
`AbstractTable`) change their parameter type:

```php
// Before
callbackFunction: static fn(TableItemModel $tableItemModel): string => $tableItemModel->renderValue(name: 'email'),

// After
callbackFunction: static fn(TableItem $tableItem): string => $tableItem->renderValue(name: 'email'),
```

Search your project for: `DbSettingsModel`, `dbSettingsModel`, `TableItemModel`, `SessionSettingsModel`,
`CspPolicySettingsModel`, `SelectOptionsSettings`, `getPhpClassName(`.

## v1.6.0 (2026-10-07)

### ⚠️ Requires `actra/yuf` `^4.10`

Was `^4.9`. yuf 4.9.1 to 4.10.0 contain security fixes that the backend uses directly:

- IP whitelists (`BackendView`, API keys) are checked with the fixed `IpValidator::isInWhitelist()` (IPv4 and IPv6
  ranges, any IPv6 notation). An invalid range in `ActraBackendSettings::$ipWhitelist` now throws an
  `InvalidArgumentException`; fix such entries.
- CSRF tokens are compared timing-safe and never read from the URL; the CSP nonce is new for every request; the session
  file is deleted when the session ID is regenerated; HSTS is kept for file responses; every response sends
  `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`.

No code change needed in `actra/backend`: its forms use POST, it uses no `TableFilter`, and the confirmation dialog
(`dialog.js`) only reads the form of the fetched page. Projects check their own code against yuf's `UPGRADE.md`
(v4.9.1 to v4.10.0), especially GET forms that change data, CSRF tokens in URLs, filter links and HTML with inline
code loaded via AJAX.

## v1.5.3 (2026-10-07)

### Requires `actra/yuf` `^4.9`

Was `^4.8`. No code change needed in `actra/backend`. yuf 4.9.0 contains a breaking change for projects that use file
uploads: `UploadedFile::getHash()` returns SHA-256 instead of SHA-1 (see yuf's `UPGRADE.md`, v4.9.0).

## v1.5.2 and older: HTML & CSS (Frontend)

### v1.5.0 – October 5, 2026

* **Feature:** The confirmation dialog (`dialog.js`) supports `data-confirm-label` on the link: the confirm button
  (`[data-action="modal-submit"]`) shows this text while the dialog is open and gets its template text back on close
  or cancel. Set as plain text. Without the attribute the behaviour is unchanged. See README "Confirmation Dialog for
  Destructive Actions".
* **Feature:** New message variant `msg-warning` (new variables `--clr-warning-50` and `--clr-warning-400` in
  `_variables.css`) next to the existing `msg-success`, `msg-note` and `msg-error` (now documented, see README
  "Assets"). All variants have a contrast of at least 4.5:1. In print, `.msg` gets a border in the text colour,
  because browsers do not print backgrounds by default.
* `user.html` and `profile.html`: the "generate API key" link points to the new confirmation pages
  `userGenerateApiKey-{ID}.html` / `profileGenerateApiKey.html` and got `data-action="confirm-deletion"`,
  `data-form="main form"`, `data-confirm` and `data-confirm-label` (new replacements `generateApiKeyConfirm` and
  `generateApiKeyConfirmLabel`). Projects with their own `user.html`/`profile.html` copy these attributes and
  replacements; the old link `?generateApiKey` no longer generates a key.
* **Migration:** Projects must rebuild their JavaScript and CSS bundles (or republish `backend.js`,
  `modules/dialog.js`, `backend.css`, `_variables.css` and `blocks/_msg.css`).

### v1.4.0 – October 4, 2026

* **Feature:** The confirmation dialog (`dialog.js`) supports POST forms: a link with `data-action="confirm-deletion"`
  and `data-form="{CSS selector}"` fetches its `href` (a confirmation page) on confirm, submits the form found by the
  selector by POST and follows the redirect. Without `data-form` the behaviour is unchanged. The listeners are
  registered once (before, they were added again on every click). See README "Confirmation Dialog for Destructive
  Actions".
* **Migration:** Projects must rebuild their JavaScript bundle (or republish `backend.js`/`modules/dialog.js`).
* `user.html`: the delete link got `data-form="main form"` and points to the new confirmation page
  `userDelete-{ID}.html`. The "remove API key" links in `user.html` and `profile.html` got `data-action="confirm-deletion"`,
  `data-form="main form"` and `data-confirm` (new text `removeApiKeyConfirm`) and point to the new confirmation pages
  `userRemoveApiKey-{ID}.html` and `profileRemoveApiKey.html`. Projects with their own `user.html`/`profile.html`
  copy these attributes and the replacement.

### v1.1.0 – October 4, 2026

* All texts of the views and page templates come from the message classes (see "Backend & API"). For a German route
  the rendered text is unchanged; a few paragraphs that were wrapped in the template source are now on one line.
* The page templates (`default.html`, `authentication.html`) use new text replacements (`skipLink`, `openMenu`,
  `closeMenu`, `cancelSessionChange`, `myProfile`, `logout`, `deleteConfirmation`, `dialogCancel`,
  `dialogConfirmDelete`). Projects with their own page templates (`templateDirectory`) can use them; their existing
  texts keep working. The `lang` attribute still comes from the route language.
* With several backend languages, the user forms, the profile and the user detail page show a language field.
* With several backend languages, both page templates show a language switcher in the header
  (`<nav class="language-switcher">`, new CSS block `src/assets/css/blocks/_language-switcher.css`, imported in
  `backend.css`). It links to the same page in each language (without query string); the current language is a
  `<span aria-current="true">`. `.auth-header` wraps its content (`flex-wrap`, `gap`). Projects with their own page
  templates can add it with the replacements `hasLanguageSwitcher`, `languageSwitcher` and `languageSwitcherLabel`.

### v1.0.0 – October 4, 2026

* Password inputs now have an `autocomplete` attribute: `current-password` on the login and the "current password"
  field, `new-password` on the fields for a new password (reset, change, create).
* The default texts generated by yuf (e.g. "Die ungültige Eingabe wurde ignoriert.", the CSRF error, the empty select
  option, the cancel link "Abbrechen") stay German; the markup of the forms is unchanged.

### v0.10.4 – July 1, 2026

* Refined the default backend CSS assets.
* Reduced the bundled Inter font declarations to the weights used by the backend UI.
* Updated backend font references to use the `/fonts/backend/` public path.
* Added reusable dropdown content styles in `src/assets/css/blocks/_dropdown-content.css`.
* Added reusable delete action styles in `src/assets/css/blocks/_delete.css`.
* Replaced the table control block with the table meta block in `src/assets/css/blocks/_table-meta.css`.
* Improved styling for buttons, icon-only buttons, tables, detail lists, authentication pages, and user dropdowns.
* Projects publishing the default assets should make sure the backend font files are available below `/fonts/backend/`.

### v0.10.3 – June 26, 2026

* Added default assets under `src/assets`.
* Added the default CSS entrypoint at `src/assets/css/backend.css`.
* Added the default JavaScript entrypoint at `src/assets/js/backend.js`.
* Added JavaScript modules for navigation toggles, dialogs, dropdowns, and responsive tables.
* Projects should integrate these assets into their own asset build or publishing process and reference the resulting
  public URLs via `ActraBackend::init()` using `stylesHref` and `javaScriptPaths`.

### v0.10.2 – June 18, 2026

* Added the `nav-user-logout` CSS class to the logout item in the user dropdown.

## v1.5.2 and older: Backend & API

### v1.5.2 – October 7, 2026

* **Security:** Logging out clears the session data of the user. Before, the next user of the same browser saw e.g.
  the breadcrumb (`$_SESSION['sess_breadcrumb']`) of the previous user. yuf 4.8.0 fixes this in `AuthSession::logOut()`:
  it removes everything except the data of the session handler, the preferred language and the CSP nonce (breadcrumb,
  table and search state, uploads, flashes, CSRF token, …).
* **Migration:** requires `actra/yuf` `^4.8` (was `^4.7.1`). Data that has to survive a logout must be written to the
  session after `AuthSession::logOut()` (see yuf's `UPGRADE.md`, v4.8.0).

### v1.5.1 – October 6, 2026

* **Migration:** requires `actra/yuf` `^4.7.1` (was `^4.7`). yuf 4.7.1 translates two texts of
  `FormMessages::german()`: the empty option of select fields (`-- Bitte auswählen --`) and the invalid option error
  (`Ungültige Auswahl im Feld [field].`). No API change; only projects (or tests) that compare these texts must adapt.

### v1.5.0 – October 5, 2026

* **Security:** Generating an API key runs only on POST (CSRF protected). New views `userGenerateApiKey`
  (`userGenerateApiKey-{ID}.html`) and `profileGenerateApiKey` (confirmation pages), new form `ApiKeyGenerateForm`
  and `actra\backend\libs\auth\GeneratedApiKeyFlash`, which keeps the new key in the session until the user or
  profile page has shown it once (it is never put into a URL).
* **Logic Change:** The GET parameter `?generateApiKey` (`user`, `profile`) is removed and no longer generates a key;
  the constants `user::PARAM_GENERATE_API_KEY` and `profile::PARAM_GENERATE_API_KEY` are removed. Projects with own
  links to it link to `userGenerateApiKey::getPath()` / `profileGenerateApiKey::getPath()` instead.
* **Feature:** New texts `CommonMessages::$generateApiKeyTitle`, `$generateApiKeyConfirm` and
  `$generateApiKeyConfirmLabel` (English and German).
* **Logic Change:** The callback columns of `UserTable`, `NotificationTable`, `TokenTable` and `VisitTable` read their
  values with the typed getters of yuf's `TableItemModel::getRow()`. An unexpected database value (e.g. `NULL` as ID,
  an unknown token type or visit result) throws a `DbRowValueException` naming the column instead of an
  `UnexpectedValueException`, `ValueError` or a link to ID 0.
* **Migration:** requires `actra/yuf` `^4.7` (was `^4.6`); yuf 4.7.0 has no breaking changes.
* Still on GET (they only switch or end the login session, no user data is changed): `?impersonate` (`user`),
  `?cancelSessionChange` (all views) and the `logout` view.
* Known issue: after jumping directly (bookmark, typed URL) from one detail page to another, the breadcrumb can still
  show the entries of the first page. Cause and proposal: `docs/breadcrumb/analysis.md` in the repository.
* No breaking change, no database changes.

### v1.4.2 – October 5, 2026

* **Logic Change:** The search of the user, token and visit tables uses `SearchHelper::createBooleanQuery()` and binds
  every search word as parameter (before, `getBooleanQuery()` interpolated the words into the SQL). A `?` in the
  search text (e.g. `?haas`) no longer throws an exception; `%`, `_` and `\` are searched literally.
* **Migration:** requires `actra/yuf` `^4.6` (was `^4.5`); yuf 4.6.0 has no breaking changes.
* No breaking change, no database changes.

### v1.4.1 – October 4, 2026

* **Logic Change:** The views read IDs from the URL with the typed yuf getters (`getRequiredPathVarAsInt()`,
  `getRequiredPathVarAsString()`). A path variable that is not strictly an integer (e.g. `user-12abc.html`,
  `user-+12.html`) now answers with a 404; before, the `(int)` cast opened the user with the leading number.
  `visits-{ID}.html` and `tokens-{ID}.html` with an invalid ID still answer with a 404.
* **Migration:** requires `actra/yuf` `^4.5` (was `^4.4`); yuf 4.5.0 has no breaking changes.
* No breaking change, no database changes.

### v1.4.0 – October 4, 2026

* **Feature:** Deleting a user and removing an API key run only on POST. New views `userDelete`
  (`userDelete-{ID}.html`), `userRemoveApiKey` (`userRemoveApiKey-{ID}.html`) and `profileRemoveApiKey`
  (confirmation pages) and the forms `UserDeleteForm` and `ApiKeyRemoveForm` (CSRF protected).
* **Feature:** New texts `CommonMessages::$removeApiKeyTitle` and `$removeApiKeyConfirm` (English and German).
* **Logic Change:** The GET parameters `?remove` (`user`) and `?removeApiKey` (`user`, `profile`) are removed and no
  longer delete; the constants `user::PARAM_REMOVE`, `user::PARAM_REMOVE_API_KEY` and `profile::PARAM_REMOVE_API_KEY`
  are removed. Projects with own links to them link to the confirmation pages `userDelete::getPath()`,
  `userRemoveApiKey::getPath()` and `profileRemoveApiKey::getPath()` instead.
* **Follow-up (not in this release):** the GET links `?generateApiKey` (`user`, `profile`) still run on GET.
* No breaking change, no database changes.

### v1.3.0 – October 4, 2026

* **Feature:** New `DB::useConnection(DbSettingsModel)`: sets the connection returned by `DB::get()` without
  `ActraBackend::init()` (integration tests, see README "Integration Tests"). Throws a `LogicException` if the
  connection already exists. No breaking change, `DB::get()` stays lazy.
* **Migration:** requires `actra/yuf` `^4.4` (was `^4.3`); yuf 4.4.0 has no breaking changes.
* **Migration:** Projects that inject `DB::$instance` with `ReflectionProperty` in their tests replace it with
  `DB::useConnection(dbSettingsModel: ...)` (once, e.g. in `tests/bootstrap.php`).

### v1.2.1 – October 4, 2026

* **Logic Change:** The repositories read database rows with the typed yuf `DbRow` (`selectRows()`, `selectRow()`)
  instead of untyped `stdClass` objects. A missing column, `NULL` in a non-nullable field or a wrong type now throws a
  `DbRowValueException`. `DbAuthUserRepository::createItem()` and the other private row mappers changed their
  signature (private, no impact).
* **Feature:** New `DB::selectRowsFromQuery()` (a `DbQuery` as `list<DbRow>`).
* **Breaking Change (internal class):** `actra\backend\libs\db\DbRowReader` is removed, use
  `actra\yuf\db\DbRow` (`getDateTime()` → `getDateTimeImmutable()`, `getNullableDateTime()` →
  `getNullableDateTimeImmutable()`, `getStringOrEmpty()` → `getNullableString() ?? ''`).
* **Migration:** requires `actra/yuf` `^4.3` (was `^4.2`). The database session time zone must match the PHP time zone.

### v1.2.0 – October 4, 2026

* **Feature:** All "now" timestamps come from the yuf `Clock` (`actra\yuf\clock\Clock`, default `SystemClock`)
  instead of the database or PHP default: `DbAuthSessionRepository::updateLastAction()`,
  `DbAuthUserRepository::sentInvitation()` and `dbConfirmSuccessfulLogin()`, `DbAuthTokenRepository::createToken()`,
  `getClaimable()` and `claim()`, and the CSV file name of `AbstractTable` (new optional last constructor argument).
  All new parameters are optional, existing calls keep working.
* **Logic Change:** Those timestamps (and the token expiry check) are now written/compared in the PHP time zone instead
  of the database session time zone. Make sure both are the same (usual setup).
* **Migration:** requires `actra/yuf` `^4.2` (was `^4.1`).

### v1.1.0 – October 4, 2026

* **Migration:** Requires `actra/yuf` `^4.1` (configurable table pagination titles); Composer updates yuf within v4.
  Attention: yuf v4.1.0 changed the default pagination titles of the project's own tables to English, see yuf's
  UPGRADE.md, section `[v4.1.0]`.
* **Feature:** All user-visible texts (forms, views, tables, navigation, emails, status labels) come from message
  classes in `actra\backend\i18n`: `BackendMessages` bundles `CommonMessages`, `LayoutMessages`, `AuthMessages`,
  `UserMessages`, `ProfileMessages`, `NotificationMessages`, `LogMessages`, `EmailMessages` and yuf's `FormMessages`.
  English and German are included (`english()`, `german()`, `forLanguageCode()`).
* **Feature:** Multilingual backend: besides the main route (`ActraBackend::init(path: ...)` in
  `ActraBackendSettings::$language`) the backend can be registered under further routes, one per language, with the
  new `ActraBackendSettings::$additionalRoutes` (`list<BackendRoute>`). The texts of a request always follow the
  language of its route: German for `de`, English for every other language, or own messages per route
  (`ActraBackendSettings::$messages` for the main route, `BackendRoute::$messages`).
  ```php
  new ActraBackendSettings(
      language: new Language(code: 'de', locale: 'de_CH'),
      ...,
      additionalRoutes: [
          new BackendRoute(path: '/en/backend/', language: new Language(code: 'en', locale: 'en_GB')),
      ]
  );
  ```
* **Feature:** Single texts can be replaced with `with()`:
  `BackendMessages::german()->with(auth: AuthMessages::german()->with(loginPageTitle: 'Login'))`.
* **Feature:** `ActraBackend::messages()` (texts of the current route), `ActraBackend::path()` (path of the current
  route), `ActraBackend::get()->getRouteForLanguage()`, `ActraBackend::get()->currentRoute`. `ActraBackend::$path` is the
  path of the main route; links should use `ActraBackend::path()`.
* **Feature:** Every user has a language (`auth_user.language`, empty = language of the main route), selectable in the
  user forms and the profile when several languages are configured. The welcome email is prefilled in the recipient's
  language and links to the backend route of that language.
* **Feature:** After login, a user with a language continues on the backend route of that language (same page). Users
  without a language stay on the route they logged in with.
* **Feature:** `ActraBackendSettings::$projectNavigation` (`BackendNavigationInterface`): projects add their own
  navigation items per route language; called at init for the main route and again for the route of each request.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/1.1.0.sql` (adds
  `auth_user.language`).
* **Logic Change:** A route whose language is not `de` and that has no messages configured now shows English texts
  (it showed German texts before).
* **Logic Change:** Date formats come from `CommonMessages::$dateFormat` / `$dateTimeFormat` (German `d.m.Y` /
  `d.m.Y H:i:s` as before, English `Y-m-d` / `Y-m-d H:i:s`).
* **Logic Change:** The visit log uses the backend's own status labels (`LogMessages::authResult()`) instead of yuf's
  `AuthResult::render()` (same German texts). `AuthTokenTypeEnum::render()` has the optional argument
  `?LogMessages $messages` (default: the texts of the current route).
* **Logic Change:** The pagination titles of the backend tables come from `CommonMessages::$paginationPrevious` /
  `$paginationNext` (German "Zurück" / "Vor" as before). `AbstractTable` has its own constructor (`identifier`, `db`,
  `dbQuery`, `itemsPerPage`) that passes them to yuf's `TablePaginationRenderer`.
* **Logic Change:** `IpWhitelistField` has the optional argument `fieldInfo` (default: `CommonMessages::$ipWhitelistInfo`).
  `DbAuthUser` has the new constructor argument `languageCode`; `DbAuthUserRepository::insert()`/`update()` the new
  parameter `languageCode`.
* **Logic Change:** `ActraBackend::get()` throws a `LogicException` before `ActraBackend::init()` (was a `TypeError`).
  `BackendView` throws an `UnauthorizedException` if the parent session of a session change no longer exists or the
  user has no allowed navigation item (both were fatal errors).
* **Feature:** `BackendView::addTexts()` adds plain texts as template replacements; `NewPasswordCheck` checks a new
  password and its confirmation (minimum length `NewPasswordCheck::MIN_LENGTH` = 8, both equal).
* **Security:** The subject, message, group and sender name on the notification detail page and the client data in
  the token log are HTML-encoded (they were rendered as HTML).

### v1.0.0 – October 4, 2026

* **Versioning:** First stable release. From now on the library follows SemVer strictly: breaking changes are
  released as a new major version only.
* **Breaking Change:** Requires `actra/yuf` `^4.0` (typed form API). yuf v3 is no longer supported, and yuf v4 is not
  supported by earlier versions of this library (e.g. the login and password forms fail at runtime).
* **Migration:** Update `actra/yuf` to `^4.0` together with this version and migrate the project's own forms as
  described in yuf's [UPGRADE.md](https://github.com/Actra-AG/yuf/blob/main/UPGRADE.md), section `[v4.0.0]` (start with
  the "Form migration checklist"; e.g. `FormMessages::german()`, `PasswordField(purpose: ...)`, typed getters instead
  of `getRawValue()`, `addError(HtmlText)`, typed rules).
* **Breaking Change:** `ValidIpAddressesRule` is replaced by `ValidIpAddressRule`, a yuf `StringRule` that checks one
  address. Add it per line with `TextAreaField::addEachRule()`; the constructor argument `errorMessage` and the
  `[ipAddress]` placeholder are unchanged.
  ```php
  // Before
  $field->addRule(formRule: new ValidIpAddressesRule(errorMessage: $invalid));
  // After
  $field->addEachRule(formRule: new ValidIpAddressRule(errorMessage: $invalid));
  ```
* **Breaking Change:** `IpWhitelistField` is a string-based yuf v4 `TextAreaField`: the constructor (`value: list<string>`)
  is unchanged, `getValue()` is replaced by `getValues()` (trimmed addresses without empty lines), `setValue()` takes the
  text (one address per line) instead of an array, and validation no longer rewrites the field value.
* **Breaking Change:** The forms (`LoginForm`, `LoginPasswordForm`, `LoginTokenForm`, `PasswordForgottenForm`,
  `PasswordResetForm`, `ProfileForm`, `ProfilePasswordForm`, `UserAddForm`, `UserInviteForm`, `UserModForm`,
  `NotificationSendForm`, `TokenSearchForm`, `UserSearchForm`, `VisitSearchForm`), `IpWhitelistField`,
  `SearchQueryField` and `SearchSelectOptionsField` are now `final`.
* **Logic Change:** `UserAddForm::$newUserID` and `NotificationSendForm::$notificationID` are `private(set)` instead of
  `readonly`; reading them is unchanged.
* **Logic Change:** Manipulated input (e.g. an array instead of a text) resets the field and shows "Die ungültige Eingabe
  wurde ignoriert." instead of keeping the previous value.
* **Feature:** Added `DbAuthGroupCollection::listFormOptionKeys()` (the option keys of `getFormOptions()`).
* **Security:** An invalid IP address shown in the IP whitelist error message is now HTML-encoded.

### 0.13.0 – July 29, 2026

* **Feature:** Added `UserDeleteHandlerInterface` for project-specific cleanup before deleting backend users.
* **Feature:** Added `UserController::registerUserDeleteHandler()` to register a user deletion handler during project
  bootstrap.
* **Logic Change:** `UserController::deleteUser()` now executes built-in cleanup, the registered delete handler, and the
  final `auth_user` deletion inside a database transaction.
* **Migration:** Projects with custom tables referencing `auth_user.ID` can either register a user deletion handler or
  use appropriate database foreign-key actions such as `ON DELETE CASCADE` or `ON DELETE SET NULL`.

### 0.12.0 – July 6, 2026

* **Feature:** Added password-based backend login using email address and password.
* **Feature:** Added a forgotten-password flow for requesting a password reset link.
* **Feature:** Added a password-reset form for setting a new password through a claimable reset token.
* **Security:** Password login still requires token confirmation after successful credential validation.
* **Security:** Login attempts respect user status, access rights, IP whitelist, missing password state, and the
  configured maximum number of wrong login attempts.
* **Database:** Added `passwordSalt`, `passwordHash`, and `wrongLoginAttempts` columns to `auth_user`.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/0.12.0.sql` before using password
  login or password reset functionality.

### v0.11.0 – July 4, 2026

* **Breaking Change:** `ActraBackend::init()` now expects an `ActraBackendSettings` object instead of individual
  configuration arguments such as language, IP whitelist, backend name, JavaScript paths, style path, login attempt
  limit, and frontend link settings.
* **Breaking Change:** The former single `stylesHref` configuration has been replaced by `stylesPaths`, allowing
  multiple stylesheet paths.
* **Feature:** Added `ActraBackendSettings` as the central configuration object for backend initialization.
* **Feature:** Added `ActraBackendSettings::$hasApi` to explicitly enable API-key functionality.
* **Logic Change:** API-key controls on profile and user detail pages are only rendered when API support is enabled.
* **Migration:** Update calls to `ActraBackend::init()` by creating and passing an `ActraBackendSettings` instance.

### 0.10.0 – June 13, 2026

* **Feature:** Added a profile page for logged-in backend users.
* **Feature:** Logged-in users can update their own first name, last name, phone number, and IP whitelist.
* **Feature:** Logged-in users can generate, replace, or remove their own API key from the profile page.
* **Feature:** Added `ActraBackend::RIGHT_BACKEND_ACCESS` as the default access right for authenticated backend users.
* **Security:** API keys can only be generated if the user has a non-empty IP whitelist.
* **Security:** A user's IP whitelist cannot be emptied while an API key exists. The API key must be removed first.
* **Database:** Added the `backend_access` right to default data and assigned it to all existing groups during upgrade.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/0.10.0.sql` to add the new
  `backend_access` right to all existing groups.

### 0.9.0 – June 13, 2026

* **Feature:** Added API-key management for backend users.
* **Feature:** User detail pages can now generate or replace a user's API key.
* **Feature:** Added bearer-token validation via `DbAuthApiKeyRepository::getUserIDForBearerOrThrow()`.
* **Security:** API keys are shown only once after generation and are stored hashed with a salt.
* **Database:** Added the `auth_api_key` table to store API-key metadata, public IDs, hashed secrets, salts, and
  registration timestamps.
* **Database:** Installations upgrading from an earlier version must apply `db/updates/0.9.0.sql` before using API-key
  functionality.
* **Logic Change:** Deleting a user now also removes that user's API key.

### 0.8.7 – May 18, 2026

* **Enhancement:** Added `cc` and `bcc` support to `Mailer::send()`.
* **Enhancement:** Added helper methods `hasOneOfIDs()` and `get()` to `DbAuthGroupCollection`.
* **Enhancement:** Added helper methods `has()` and `get()` to `DbAuthUserCollection`.
* **Logic Change:** The template directory is now configurable via `ActraBackend::init()`. The default remains
  `__DIR__ . '/view/backend/templates/'` within `ActraBackend`.
* **Logic Change:** Added `legacyBreadcrumbSeparator` to `BackendView` constructor to allow customizing the separator in
  breadcrumbs (defaults to `' '`).
* **Refactor:** `ActraBackend::renderJavaScriptPaths()` and other methods updated for better code style consistency.
