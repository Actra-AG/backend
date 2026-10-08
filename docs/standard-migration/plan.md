# Plan: bring `actra/backend` to yuf ^4.57 and the global coding standard

Goal: the same standard in all Actra projects, without project deviations. This plan replaces the task list of
2026-10-07 (state v1.6.0, yuf ^4.10). It includes the remaining tasks of `docs/coding-standard/plan.md` (tooling).

Sources: `../yuf/docs/standard-migration/remaining.md` (follow-up for the backend), `../yuf/UPGRADE.md`
(v4.10.1–v4.57.0) and four read-only impact analyses of these releases against `src/`, `tests/` and `README.md`
(2026-10-08, summarized per step below).

## Rules for every step

- One step = one release (minor version, see `standards/versioning.md`), small enough to be released on its own.
  `ddev composer check` is green at the end of every step, the PHPStan baseline only shrinks.
- yuf steps raise `actra/yuf` to the lowest version that contains the step's changes, so each release works with a
  real yuf version. From step 8 on the constraint locks the minor version (`~4.37.0`, decision 2026-10-08, coding
  standard v1.7.0): with `^`, projects got newer yuf versions the backend did not support yet.
- Every step adds an `UPGRADE.md` section with ⚠️ before/after for each breaking change and a "Search your project
  for …" list (like yuf), updates `README.md` where affected and appends a handover note below.
- Characterization tests first where behaviour changes; hand-written doubles in `tests/Double/`, no reflection on
  private code, no `self::` for own constants, lines ≤ 120 characters.
- No git commands that change the index, the working tree or history; the user commits. Never rename files only by
  case. PHP, Composer and PHP-CS-Fixer only through DDEV (`ddev composer cs`, `cs:fix`, `check`). Temporary files in
  `/tmp`. `../yuf` and other projects are not modified.
- Steps are done directly in the main session, which already knows the code and the decisions; mechanical changes
  (renames over many files) with scripts. A Sonnet session (Agent tool) only for a long, self-contained job with a
  precise specification, never for a small step (the warm-up costs more than the work). It runs only the affected
  tests while working and the full `ddev composer check` once at the end. Every step is reviewed before the commit
  message is proposed (check, baseline, diff, `git status`, browser if views or HTML changed).
- Every step also checks the project documentation against the current `actra/coding-standard`: `AGENTS.md` keeps
  only project-specific rules, and a temporary deviation is removed as soon as its step is done.

## Consumer impact (why the order matters)

Read-only count in 11 consuming projects in `../`: 245 views extend `BackendView` (every one has its own
constructor), 83 tables extend `AbstractTable`, 45 forms extend `AbstractSearchForm`, `DB::get()` is called 416
times, `MyAuthUser::get()` 33 times, `Mailer::send…()` 34 times, `DbAuthUserRepository` and other repositories about
50 times. Every signature change of these base classes means editing hundreds of project files. Therefore:

- **Decision (2026-10-08):** the constructors of `BackendView`, `AbstractTable` and `AbstractSearchForm` change only
  once. They take a `BackendViewContext` (yuf's `ViewContext` plus the services of the backend). Project routes get
  their views from a view factory of the backend. Later steps add services to `BackendViewContext` (messages, path,
  db, repositories, current user, mailer) without touching project constructors again.
- Static accessors used by projects (`DB::get()`, `MyAuthUser::get()`, `Mailer::send…()`, repositories) stay as
  deprecated wrappers for one release after their replacement exists in `BackendViewContext`.

Browser checks: `../drogeriehaas.ch` (decision 2026-10-08). It installs `actra/backend` from Packagist today; the
user points it to `../backend` as Composer path repository and adapts it to each step (other projects are not
modified from this repository). Steps that change views, templates or HTML are checked there.

## Decisions

- `BackendViewContext` once, backend view factory for project routes (see above).
- Legacy breadcrumb: migrate `OldNavigator` to yuf's `Session` and fix the stale trail with the proposal of
  `docs/breadcrumb/analysis.md` (step 6).
- API keys with `SecretTokenHash` in step 8 (decision 2026-10-08, after the Argon2id discussion).
- No PascalCase for view classes (decision 2026-10-08, former step 16): the views keep the lowercase file name as
  class name, a documented deviation in `AGENTS.md` (allowed by yuf v4.57.3, same as yuf-skeleton). Constructor
  dependencies come through `BackendViewFactory` and `BackendViewContext`; project views use the same factory and
  stay lowercase anyway.
- Settled by the standard, no decision needed: renamed argument names (`dbSettings:`), snake_case database, acronyms,
  CSV formula protection of yuf (security standard), stricter phone validation of yuf (no switch exists).

## Steps

| #  | yuf   | Content                                                                    | Size   | Who    | Release |
|:---|:------|:---------------------------------------------------------------------------|:-------|:-------|:--------|
| 0  | ^4.10 | Tooling green (code style, test errors, coding standard ^1.3)              | small  | direct | –       |
| 1  | ^4.14 | `DbSettings`, `TableItem`                                                  | small  | direct | v1.7.0  |
| 2  | ^4.15 | `BackendViewContext`, view factory, `ViewContext` in all views             | large  | direct | v1.8.0  |
| 3  | ^4.34 | yuf v4.16–v4.34 in one step (former steps 3, 4, 5 and 7, see below)        | large  | direct | v1.9.0  |
| 4  | –     | merged into step 3                                                         | –      | –      | –       |
| 5  | –     | merged into step 3                                                         | –      | –      | –       |
| 6  | ^4.34 | No `$_SESSION`; breadcrumb on `Session` and fixed                          | medium | direct | v1.10.0 |
| 7  | –     | merged into step 3                                                         | –      | –      | –       |
| 8  | ^4.37 | Passwords (`dbUpdatePassword()`), API keys with `SecretTokenHash`, tables  | medium | direct | v1.11.0 |
| 9  | ^4.41 | Mailer, form attributes, `IpTypeEnum::IP`                                  | small  | direct | v1.12.0 |
| 10 | ^4.57 | Search state, resolved route, navigation, `createHtmlTag()`, rest          | medium | direct | v1.13.0 |
| 11 | ^4.57 | Empty baseline, line lengths, no superglobals rule, constant names         | small  | direct | v1.14.0 |
| 12 | ^4.57 | `final`, extension points, `@internal`                                     | medium | direct | v1.15.0 |
| 13 | ^4.57 | Interfaces without suffix                                                  | small  | direct | v1.16.0 |
| 14 | ^4.57 | No static `ActraBackend`: messages, path, navigation in the context        | large  | direct | v1.17.0 |
| 15 | ^4.57 | Repositories, `DB`, current user, mailer as services                       | large  | direct | v1.18.0 |
| 16 | ^4.57 | Acronyms of the backend API (`ID` → `id`, …)                               | large  | Sonnet | v1.19.0 |
| 17 | ^4.57 | snake_case database tables and columns                                     | large  | Sonnet | v1.20.0 |

`^4.57` in the table means `^4.57.3` (the current yuf release). "Sonnet" marks the only steps that are long, mechanical and self-contained enough for a separate session; the main
session decides again when the step starts. The release numbers are the expected order; a major version (v2.0.0) for
the final state is an open decision.

### Step 0 – tooling green (no release)

Remaining tasks 1 and 2 of `docs/coding-standard/plan.md`, before any API change, so every later step can be checked:

- Raise `actra/coding-standard` to ^1.3 (v1.2: naming without `Model`; v1.3: opt-in superglobals rule, not enabled
  yet).
- `ddev composer cs:fix` as its own `style` change; review the risky fixers (`use_arrow_functions`,
  `no_trailing_whitespace_in_string`, `modifier_keywords`).
- Fix the 11 PHPStan errors in `tests/` (`#[\Override]`, offset checks, `markTestSkipped()` statically).
- Align with the coding standard v1.3: configuration files compared with its templates (only `scanDirectories` for
  yuf differs, yuf has no Composer autoload), rules of `AGENTS.md` that repeat the global standard removed,
  `docs/coding-standard/plan.md` closed (its last task, the baseline, is step 11).

### Step 1 – yuf ^4.14 (v4.10.1–v4.14.0)

- `DbSettingsModel` → `DbSettings`: `ActraBackend::init(dbSettings:)`, `ActraBackend::$dbSettings`,
  `DB::useConnection(dbSettings:)`, `DBTest`, README (⚠️ every project changes its `init()` call).
- `TableItemModel` → `TableItem` in the callbacks of `UserTable`, `TokenTable`, `VisitTable`, `NotificationTable`.
- Behaviour notes: session ID only from the cookie (v4.10.1).

### Step 2 – yuf ^4.15: `BackendViewContext` (the one constructor change for project views)

- New `BackendViewContext` (final, readonly): the yuf `ViewContext` plus, for now, the `ActraBackend` instance.
  `BackendView::__construct(BackendViewContext $context, …)` passes `$context->viewContext` to `BaseView`.
- A view factory of the backend (`ActraBackend::createViewFactory()`, class-name based like yuf's
  `ClassNameViewFactory`) creates `new $className(context: $backendViewContext)`; the backend routes and the project
  routes with `BackendView` views use it.
- All 27 backend views take `BackendViewContext`; `RequestHandler::get()`, `ContentHandler::get()` and
  `HtmlDocument::get()` in `BackendView` are replaced by the context (`route`, `pathVars`, `content`,
  `getHtmlDocument()`).
- Decide in this step with a sketch in the handover note: what `AbstractTable` and `AbstractSearchForm` receive
  (`BackendViewContext`), so their constructors also change only once (they change in steps 4 and 5).
- ⚠️ Projects: every view constructor, every route with backend views. UPGRADE.md with a search list.

### Step 3 – yuf ^4.34 (v4.16–v4.34, former steps 3, 4, 5 and 7)

yuf v4.18.0–v4.31.0 ship `FrameworkDb`, `CsvFile`, `SmtpMailer` and `SimpleXmlExtended` in files with the old case
(`FrameworkDB.php`, …); `actra/autoloader` cannot load them on a case-sensitive file system (DDEV, Linux servers).
yuf v4.32.0 fixed the file names. A backend release requiring ^4.23, ^4.28 or ^4.30 would not work, so these steps are
one step and one release against yuf ^4.34 (decision 2026-10-08). Former step 6 (`$_SESSION`, breadcrumb) does not
depend on these versions and follows as its own step.

#### Part from v4.16–v4.23

- `AuthResult` / `AuthMethod` → `…Enum`; `MyAuthUser::$ID` → `$id` (inherited); `MyAuthenticator::logAuthResult()`
  arguments `userId`, `sessionId`; `getAuthSessionId()`, `logIn(authSessionId:)`, `getId()`.
- `HttpRequest::getUri()`; `CsvFile`, `FrameworkDb`, `SmtpMailer`; table constants (`FILTER`, `PAGINATION`,
  `TOTAL_AMOUNT`, `TABLE`, `AMOUNT`, same HTML).
- ⚠️ Projects: `MyAuthUser::get()->ID` → `->id`. Every user is logged out once (session key `authSessionId`).

#### Part from v4.24–v4.28

- `HtmlText::unencoded()` → `fromText(text:)`, `encoded()` → `fromHtml(html:)`, `addEncodedText()` → `addHtml()`,
  `addUnencodedText()` → `addText()`, `addTextElement()`, `DetailDataObject(isHtml:)` (about 90 files, mechanical;
  check every `addHtml` / `fromHtml` for user data).
- New template engine: `AbstractTable` gets the template engine from the context (constructor per step 2 decision);
  verify the templates (`loadSubTpl`, `if` comparisons, `else`) in the browser.
- ⚠️ Projects: tables (if not already covered by step 2), own templates, delete compiled templates in the cache.

#### Part from v4.29–v4.30

- `HttpRequest` instance: `getUri()`, `getRemoteAddress()`, `getProtocol()->value`, `getBearerToken()`;
  `InputParameter(source: InputSourceEnum::QUERY)`; `redirectAndExit(httpRequest:)`; `CsvFile::pushDownloadAndExit()`.
- Repositories and helpers that read the request get it as argument (`DbAuthSessionRepository::insert()`,
  `DbAuthTokenRepository::createToken()`, `DbAuthApiKeyRepository::getUserIdForBearerOrThrow()`,
  `Mailer::send…()` with the server address); keep project-facing signatures stable where the context can supply it.
- `Session`, `AuthSession` instance, `FormContext` in all 18 forms, `SearchHelper::create()`, `DbResultTable` with
  request and session, `MyAuthUser::get(…)`, `MyAuthenticator` with request and auth session, `UserController`.
- ⚠️ Projects: forms (`context:`), search forms, repository calls, one-time logout (yuf session data moves under
  `$_SESSION['yuf']`).

### Step 6 – yuf ^4.34: no `$_SESSION`, breadcrumb fixed

- `AuthTokenTypeEnum`, `GeneratedApiKeyFlash`, `MyAuthUser` (page after login) use `Session`; tests with
  `ArraySessionStorage` instead of `$_SESSION = []`.
- `OldNavigator` on `Session` and `HttpRequest` (no `$_GET`), fixed with the proposal of
  `docs/breadcrumb/analysis.md` section 4 (characterization tests of the current trail first).
- Required before yuf v4.46 (lazy session start).

#### Part from v4.31–v4.34 (belongs to step 3)

- `new DB(connectionParameters: DbConnectionParameters::forMysql(dbSettings:))`; `DbSettings` without `identifier`
  (`DBTest`, README); `getLastInsertId()` in 4 repositories.
- Behaviour notes: CSV cells starting with `=`, `+`, `-`, `@` get a `'` (also phone numbers in exports);
  `Content-Language` header; Italian phone numbers.

### Step 8 – yuf ^4.37 (v4.35–v4.37): passwords and API keys

- `MyAuthUser::dbUpdatePassword()` (new abstract method) with a repository method that only updates salt and hash
  (lazy upgrade to Argon2id at login); a constant hash for users without password instead of
  `Password::generateNew()` per instance.
- API keys with yuf's `SecretTokenHash` (decision 2026-10-08): new keys store the SHA-256 of a random secret (`salt`
  `''`), keys with a salt keep the legacy check until they are regenerated. `DbAuthApiKey::$key` changes its type
  (⚠️). In the same release as the yuf raise, because from v4.37 on `Password::generateNew()` creates Argon2id
  hashes, which would cost one Argon2id check (~50 ms, 64 MB) per API request. README ("stored hashed").
- `$totalAmountMessageOneResult` / `…NumResults` (v4.35).
- Behaviour notes: Argon2id with empty `passwordSalt`, `auth_session.sessionId` is the ID before login, SMTP TLS fails
  closed (README `MailerSettings`), no `Bcc` header.

### Step 9 – yuf ^4.41 (v4.38–v4.41)

- `IpTypeEnum::IP`; `HtmlTagAttribute::fromText()` in `SearchQueryField` / `SearchSelectOptionsField`.
- Behaviour notes: fixed production error texts, `NavigationItem` href check, IP whitelist fails closed, escaped
  plain text values in form markup.

### Step 10 – yuf ^4.57.3 (v4.42–v4.57.3)

- `SearchState::create()` in `AbstractSearchForm`, `SearchQueryBuilder::createBooleanQuery()` in 3 tables;
  `ViewContext` route and `PathVars` (v4.49); `createHtmlTag()` (v4.51); `toTemplateData()` in `LanguageSwitcherTest`;
  dead code after `redirectAndExit()` (`never`).
- Navigation (v4.52 throws for a duplicate key): add the backend and project items once per request, not at
  `init()` and again at `activateRoute()`; `BackendNavigationInterface` doc and README ("called once per request").
- Behaviour notes: stricter phone validation, SMTP AUTH method from the server, `Core::fromEnvironment()` in the
  project's `index.php`, language needs a session, IPv4-mapped IPv6 in whitelists.
- Optional (open point): Microsoft Graph mailer.

### Step 11 – remaining standard checks

- i18n (`standards/i18n.md`, findings 2026-10-08): dates are formatted with `date()` patterns from the messages
  (`CommonMessages::$dateFormat`, `$dateTimeFormat`) instead of `IntlDateFormatter`; full names are concatenated
  (`firstName . ' ' . lastName` in `MyAuthUser`, `NotificationTable`, `DbAuthUserCollection`,
  `DbAuthUserNotification`, `user`, `userDelete`) instead of a message with placeholders. Templates and the language
  per request comply.
- `AuthTokenTypeEnum::ACTIVATION` throws `new Exception('To be implemented')`: a specific SPL exception or remove the
  case (check whether projects use it).
- Tooling like yuf-skeleton: include `vendor/actra/coding-standard/config/phpstan-no-superglobals.neon` (no
  `actraSuperglobalsAllowIn` needed after step 6); `phpunit.xml` ends with a newline.
- PHPStan baseline to 0 entries; lines ≤ 120 (51 today); `self::` in `DBTest`; `ActraBackend::viewGroup` →
  `VIEW_GROUP` (deprecated alias); enable `config/phpstan-no-superglobals.neon` of the coding standard.

### Step 12 – `final`, extension points, `@internal`

- Extension points (stay open, "Extension point: …" PHPDoc like yuf): `BackendView`, `AbstractTable`,
  `AbstractSearchForm`. Everything else `final`. Classes not meant for projects (views, forms, internal helpers) get
  `@internal`; classes projects use (`DB`, repositories, records, `Mailer`, `SearchQueryField`,
  `SearchSelectOptionsField`, settings) stay API.

### Step 13 – interfaces without suffix

- `UserDeleteHandler`, `BackendNavigation`; the old names stay as deprecated interfaces extending the new ones for one
  release.

### Step 14 – no static `ActraBackend`

- `ActraBackend::init()` returns the instance used by the view factory; `BackendViewContext` gets `messages`, the
  current backend route (`path`) and the navigation; the static `getPath()` / `getNavigationItem()` of the views move
  to a route paths object. `ActraBackend::get()`, `messages()`, `path()` become deprecated wrappers.

### Step 15 – services instead of static classes

- Repositories as instances with a `DB` dependency, `DB` without `get()`, the current user
  (`BackendViewContext::$currentUser`) instead of `MyAuthUser::get()`, `MyAuthenticator` and `Mailer` as services,
  `AuthTokenTypeEnum` without I/O (logic in a service). Static methods stay as deprecated wrappers for one release
  (416 `DB::get()` calls in projects). Possibly split into 14a (db, repositories) and 14b (user, auth, mailer).

### Step 16 – acronyms of the backend API

- `ID` → `id`, `userID` → `userId`, `getUserIDForBearerOrThrow()` → `…Id…` in properties, methods and argument names;
  properties readable under the old name for one release (deprecated property hook), renamed arguments are breaking.

### Step 17 – snake_case database

- `auth_ipWhitelist` → `auth_ip_whitelist`, all camelCase columns to snake_case (`authUserID` → `auth_user_id`,
  `ID` → `id`) with `db/updates/<version>.sql` (`RENAME TABLE`, `RENAME COLUMN`), `schema.sql`, `data.sql` and all
  repositories; characterization tests of the row mapping first. Released alone (projects that query the tables).

## Open points

- Microsoft Graph mailer (yuf v4.56): `Mailer` supports SMTP only; Microsoft 365 no longer allows SMTP basic auth.
- `DBTest` against SQLite (`DbConnectionParameters`, yuf v4.34) instead of skipping without MariaDB.
- Major version for the final state (v2.0.0) instead of v1.23.0.

## Handover notes

### Step 0 – done (2026-10-08)

- `actra/coding-standard` ^1.3 (v1.3.0 installed). The configuration files match its templates; only
  `scanDirectories` for yuf differs (yuf still has no Composer autoload).
- `composer cs:fix` changed 127 files. Risky fixers checked: `use_arrow_functions` (4 table callbacks without `use`,
  same behaviour), `no_trailing_whitespace_in_string` (only line ends inside SQL strings), `modifier_keywords`
  (`private(set)` → `public private(set)`, same visibility).
- The 11 PHPStan errors in `tests/` fixed: `#[\Override]`, `?? <Test>::fail()` instead of unchecked offsets, entries
  compared as one list, `markTestSkipped()` and `fail()` called statically. `ddev composer check` is green
  (85 tests); the baseline is unchanged (144 entries, all in `src/`).
- `AGENTS.md`: rules that repeat the global standard removed (exceptions, settings objects, security features, generic
  JavaScript rules, README updates, export-ignore, DDEV start); `docs/coding-standard/plan.md` closed.
- Open for step 11: 6 lines over 120 characters in test files that only the fixer touched.

### Step 1 – done (2026-10-08)

- `actra/yuf` ^4.14, checked against exactly v4.14.0 (`composer update actra/yuf --with actra/yuf:4.14.0`; every
  yuf step is checked against its lowest version this way).
- `DbSettings` / `dbSettings` in `ActraBackend::init()`, `ActraBackend::$dbSettings`, `DB::useConnection()`,
  `DBTest`, README; `TableItem` / `$tableItem` in the table callbacks. No other change of v4.10.1–v4.14.0 affects the
  backend. `ddev composer check` green, baseline unchanged (144).
- README: the list of yuf versions per backend version is replaced by a pointer to `composer.json` and `UPGRADE.md`.

### Step 2 – done (2026-10-08)

- `actra/yuf` ^4.15, checked against v4.15.0.
- `BackendViewContext` (final, readonly): `viewContext` (yuf) and `actraBackend`. Later steps add `messages`, the
  current route, `db`, repositories, the current user and the mailer here.
- `BackendViewFactory` (yuf `ViewFactory`): class name like `ClassNameViewFactory`; subclasses of `BackendView` get
  `new $class(context: BackendViewContext)`, other views `new $class(context: ViewContext)`, a class that is no view
  throws. Backend routes use it; projects get it with `ActraBackend::get()->createViewFactory()`.
- `BackendView::__construct(BackendViewContext $context, …)` keeps it as `$this->backendContext`; `$this->context` is
  the yuf `ViewContext`. `RequestHandler::get()`, `ContentHandler::get()` and `HtmlDocument::get()` are gone from
  `BackendView`; the breadcrumb gets the path variables from `PathVars` (rebuilt as list, `OldNavigator` needs it until
  step 6). `ActraBackend::get()` in `BackendView` replaced by the context.
- Decision of this step: `AbstractTable(BackendViewContext $context, string $identifier, DbQuery $dbQuery,
  int $itemsPerPage = 25, ?FrameworkDB $db = null, Clock $clock)` and `AbstractSearchForm(BackendViewContext $context,
  string $name)` change now, so steps 4, 5 and 15 take the template engine, request, session and database from the
  context without another signature change. `db` defaults to `DB::get()`.
- `BackendViewFactoryTest` (4 tests) with doubles in `tests/Double/` (`ActraBackendTestInstance` initializes the
  backend once with example settings, no database connection). 89 tests, baseline unchanged (144).
- Browser check in `../drogeriehaas.ch` pending: the user adapts its views, tables, search forms and routes.

### Between steps 2 and 3 (2026-10-08)

- `actra/coding-standard` ^1.4.1 (v1.4.0: `standards/i18n.md`, library rules; v1.4.1: documentation only).
- `AGENTS.md`: the forms rules are replaced by a link to "Rules for forms" in yuf's README (yuf v4.57.2); every old
  rule is covered there, the rule about typed getters is obsolete (yuf has no untyped getters any more). The
  `vendor/` copy has the section from step 10 on.
- yuf's follow-up prompt asked to raise yuf to ^4.57.2 at once; that stays step 10 of this plan (now ^4.57.2), so every
  step keeps a released yuf version.
- i18n findings added to step 11.

### Coding standard v1.5.0, yuf v4.57.3, yuf-skeleton (2026-10-08)

- `actra/coding-standard` ^1.5.0 (v1.5.0: global rules go into the coding standard first). `ddev composer check`
  green.
- yuf v4.57.1–v4.57.3 are documentation only ("Rules for forms", lowercase class names of `ClassNameViewFactory`
  views). Target of step 10 is ^4.57.3.
- Former step 16 (PascalCase view classes) dropped, see Decisions; steps 17 and 18 are now 16 and 17.
- yuf-skeleton compared (tooling only): same coding standard configuration, composer scripts, `.editorconfig` and
  `CLAUDE.md`. Worth taking over (step 11): `phpstan-no-superglobals.neon`, final newline of `phpunit.xml`. Its
  deviation wording for lowercase view classes is used in `AGENTS.md`.
- Next: step 3 (yuf ^4.23), no decision needed.


### Step 3 – done (2026-10-08)

- `actra/yuf` ^4.34 (checked against v4.34.0; intermediate checks against v4.23.0, v4.28.0 and v4.30.0 while working,
  where only `DBTest` failed because of the file names), `actra/coding-standard` ^1.6.0.
- v4.16–v4.23: `AuthResultEnum`, `AuthMethodEnum`, `MyAuthUser::$id` (inherited), `logAuthResult(userId:,
  sessionId:)`, `getAuthSessionId()`, `getId()`, `getUri()`, `FrameworkDb`, `CsvFile`, `SmtpMailer`, table constants.
- v4.24–v4.28: `fromText()` / `fromHtml()`, `addHtml()` / `addText()` (script with a parser for nested calls). Data
  objects that were output unescaped (group name, whitelist IPs, asset paths) are escaped now (`addText()`); the
  notification details stay HTML (encoded before). `AbstractTable` gets the template engine from the context. The
  templates were checked by search against the removed and stricter constructs of the new engine (none used; all `if`
  compare with `true`, `false`, `null` or `""`); rendering them is part of the browser check after the plan.
- v4.29–v4.30: decision of this step: `ActraBackend::activateRequest(ViewContext)` (called by `BackendView`) keeps
  the request; `getViewContext()`, `findViewContext()`, `getAuthSession()` serve the static helpers (`MyAuthUser`,
  `MyAuthenticator`, repositories, emails, `UserController`), so their project-facing signatures stay until step 15.
  Exception: `getUserIDForBearerOrThrow(HttpRequest)` (API views have no backend request). Forms take the
  `BackendViewContext` (request via `Form::$context`), `AbstractSearchForm` reads `POST` values, `AbstractTable` gets
  request and session from the context, `InputParameter` with `QUERY`, `redirectAndExit(httpRequest:)`. `Mailer` uses
  the server address of the request or `gethostname()` (CLI).
- v4.31–v4.34: `DbConnectionParameters::forMysql()`, `getLastInsertId()`, `DbSettings` without `identifier`.
- `tests/Double/ViewContextFactory` builds the `ViewContext` with an in-memory session. 89 tests, baseline 144 → 138
  entries. No new line over 120 characters (single-line calls split by a script).
- Open for step 6: `$_SESSION` in `MyAuthUser` (page after login), `GeneratedApiKeyFlash`, `AuthTokenTypeEnum`,
  `OldNavigator`; `GeneratedApiKeyFlashTest` still sets `$_SESSION`.

### Step 6 – done (2026-10-08)

- Characterization tests of `OldNavigator` first (7 cases on `$_SESSION` / `$_GET`), then the same cases against the
  new `SessionBreadcrumbTrail` (`@internal`, `Session` and `HttpRequest`, same session keys); `OldNavigator` and its
  test removed. The link of a trail entry is escaped (was raw from the URL).
- Breadcrumb fix (decision 2026-10-08: overridable method): `BackendView::getBreadcrumbParents():
  ?BreadcrumbItemCollection`, called after `prepareHtmlDocument()`; with parents the breadcrumb is built from them
  (same markup) and the session trail restarts at the page. 307 project views use `useNavigator: true` and keep
  working unchanged.
- `GeneratedApiKeyFlash` and the session methods of `AuthTokenTypeEnum` take the `Session` (projects use only the
  cases of the enum); `MyAuthUser` (page after login) uses `ActraBackend::getSession()`. `claim()` uses its own type.
- No superglobal left in `src/`; tests use `ArraySessionStorage`. 103 tests, baseline 138 → 117 entries (a
  regeneration had picked up two `tests/` entries, removed again: `tests/` never has baseline entries).

### Step 8 – done (2026-10-08)

- `actra/yuf` `~4.37.0` (checked against v4.37.0). Finding: with `^4.x`, backend v1.7.0–v1.10.0 get yuf v4.57 in
  projects and fail (v1.10.0: `dbUpdatePassword()` missing). Decision: libraries lock the minor version; rule added to
  the coding standard (`versioning.md` section 8, v1.7.0 in `../coding-standard`, not yet tagged).
- Passwords: `MyAuthUser::dbUpdatePassword()` and `DbAuthUserRepository::updatePasswordHash()` (keeps the wrong
  attempts). The backend checks the password itself in `LoginPasswordForm` and logs in with the token afterwards, so
  yuf's rehash never runs: the form upgrades outdated hashes after a successful check. Every rejection before the
  check spends the verification time (`Password::spendVerificationTime()`), otherwise Argon2id would reveal existing
  email addresses by the response time. Users without password get a constant hash `'!'` (matches no input).
- API keys: new keys `SecretTokenHash::fromSecret()` (hex secret stays, the bearer format splits at `_`), empty salt;
  `createKeyHash()` chooses `SecretTokenHash` for an empty salt with 64 hex characters, otherwise `Password` (salted
  legacy keys, and Argon2id keys created with yuf v4.37+ before this release). `DbAuthApiKey::isValid()`.
- `db/updates/1.11.0.sql`: `passwordHash` `varchar(255)` (yuf v4.37). Tables: renamed message properties (v4.35),
  `#[\Override]` on `render()`.
- 106 tests, baseline 117 → 111 entries.
