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
  real yuf version.
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
- Settled by the standard, no decision needed: renamed argument names (`dbSettings:`), snake_case database, acronyms,
  CSV formula protection of yuf (security standard), stricter phone validation of yuf (no switch exists).

## Steps

| #  | yuf   | Content                                                                    | Size   | Who    | Release |
|:---|:------|:---------------------------------------------------------------------------|:-------|:-------|:--------|
| 0  | ^4.10 | Tooling green (code style, test errors, coding standard ^1.3)              | small  | direct | –       |
| 1  | ^4.14 | `DbSettings`, `TableItem`                                                  | small  | direct | v1.7.0  |
| 2  | ^4.15 | `BackendViewContext`, view factory, `ViewContext` in all views             | large  | direct | v1.8.0  |
| 3  | ^4.23 | Auth and acronym names of yuf, static handlers gone                        | medium | direct | v1.9.0  |
| 4  | ^4.28 | `HtmlText` / replacement names, template engine, tables                    | medium | direct | v1.10.0 |
| 5  | ^4.30 | `HttpRequest` instance, `Session`, `AuthSession`, `FormContext`            | large  | direct | v1.11.0 |
| 6  | ^4.30 | No `$_SESSION`; breadcrumb on `Session` and fixed                          | medium | direct | v1.12.0 |
| 7  | ^4.34 | Db connection, `CsvFile`, `getLastInsertId()`                              | small  | direct | v1.13.0 |
| 8  | ^4.37 | Passwords (`dbUpdatePassword()`), API keys with `SecretTokenHash`, tables  | medium | direct | v1.14.0 |
| 9  | ^4.41 | Mailer, form attributes, `IpTypeEnum::IP`                                  | small  | direct | v1.15.0 |
| 10 | ^4.57 | Search state, resolved route, navigation, `createHtmlTag()`, rest          | medium | direct | v1.16.0 |
| 11 | ^4.57 | Empty baseline, line lengths, no superglobals rule, constant names         | small  | direct | v1.17.0 |
| 12 | ^4.57 | `final`, extension points, `@internal`                                     | medium | direct | v1.18.0 |
| 13 | ^4.57 | Interfaces without suffix                                                  | small  | direct | v1.19.0 |
| 14 | ^4.57 | No static `ActraBackend`: messages, path, navigation in the context        | large  | direct | v1.20.0 |
| 15 | ^4.57 | Repositories, `DB`, current user, mailer as services                       | large  | direct | v1.21.0 |
| 16 | ^4.57 | PascalCase view classes                                                    | medium | direct | v1.22.0 |
| 17 | ^4.57 | Acronyms of the backend API (`ID` → `id`, …)                               | large  | Sonnet | v1.23.0 |
| 18 | ^4.57 | snake_case database tables and columns                                     | large  | Sonnet | v1.24.0 |

"Sonnet" marks the only steps that are long, mechanical and self-contained enough for a separate session; the main
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

### Step 3 – yuf ^4.23 (v4.16–v4.23)

- `AuthResult` / `AuthMethod` → `…Enum`; `MyAuthUser::$ID` → `$id` (inherited); `MyAuthenticator::logAuthResult()`
  arguments `userId`, `sessionId`; `getAuthSessionId()`, `logIn(authSessionId:)`, `getId()`.
- `HttpRequest::getUri()`; `CsvFile`, `FrameworkDb`, `SmtpMailer`; table constants (`FILTER`, `PAGINATION`,
  `TOTAL_AMOUNT`, `TABLE`, `AMOUNT`, same HTML).
- ⚠️ Projects: `MyAuthUser::get()->ID` → `->id`. Every user is logged out once (session key `authSessionId`).

### Step 4 – yuf ^4.28 (v4.24–v4.28)

- `HtmlText::unencoded()` → `fromText(text:)`, `encoded()` → `fromHtml(html:)`, `addEncodedText()` → `addHtml()`,
  `addUnencodedText()` → `addText()`, `addTextElement()`, `DetailDataObject(isHtml:)` (about 90 files, mechanical;
  check every `addHtml` / `fromHtml` for user data).
- New template engine: `AbstractTable` gets the template engine from the context (constructor per step 2 decision);
  verify the templates (`loadSubTpl`, `if` comparisons, `else`) in the browser.
- ⚠️ Projects: tables (if not already covered by step 2), own templates, delete compiled templates in the cache.

### Step 5 – yuf ^4.30 (v4.29–v4.30, one refactoring)

- `HttpRequest` instance: `getUri()`, `getRemoteAddress()`, `getProtocol()->value`, `getBearerToken()`;
  `InputParameter(source: InputSourceEnum::QUERY)`; `redirectAndExit(httpRequest:)`; `CsvFile::pushDownloadAndExit()`.
- Repositories and helpers that read the request get it as argument (`DbAuthSessionRepository::insert()`,
  `DbAuthTokenRepository::createToken()`, `DbAuthApiKeyRepository::getUserIdForBearerOrThrow()`,
  `Mailer::send…()` with the server address); keep project-facing signatures stable where the context can supply it.
- `Session`, `AuthSession` instance, `FormContext` in all 18 forms, `SearchHelper::create()`, `DbResultTable` with
  request and session, `MyAuthUser::get(…)`, `MyAuthenticator` with request and auth session, `UserController`.
- ⚠️ Projects: forms (`context:`), search forms, repository calls, one-time logout (yuf session data moves under
  `$_SESSION['yuf']`).

### Step 6 – yuf ^4.30: no `$_SESSION`, breadcrumb fixed

- `AuthTokenTypeEnum`, `GeneratedApiKeyFlash`, `MyAuthUser` (page after login) use `Session`; tests with
  `ArraySessionStorage` instead of `$_SESSION = []`.
- `OldNavigator` on `Session` and `HttpRequest` (no `$_GET`), fixed with the proposal of
  `docs/breadcrumb/analysis.md` section 4 (characterization tests of the current trail first).
- Required before yuf v4.46 (lazy session start).

### Step 7 – yuf ^4.34 (v4.31–v4.34)

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

### Step 10 – yuf ^4.57 (v4.42–v4.57)

- `SearchState::create()` in `AbstractSearchForm`, `SearchQueryBuilder::createBooleanQuery()` in 3 tables;
  `ViewContext` route and `PathVars` (v4.49); `createHtmlTag()` (v4.51); `toTemplateData()` in `LanguageSwitcherTest`;
  dead code after `redirectAndExit()` (`never`).
- Navigation (v4.52 throws for a duplicate key): add the backend and project items once per request, not at
  `init()` and again at `activateRoute()`; `BackendNavigationInterface` doc and README ("called once per request").
- Behaviour notes: stricter phone validation, SMTP AUTH method from the server, `Core::fromEnvironment()` in the
  project's `index.php`, language needs a session, IPv4-mapped IPv6 in whitelists.
- Optional (open point): Microsoft Graph mailer.

### Step 11 – remaining standard checks

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

### Step 16 – PascalCase view classes

- The backend view factory maps the file titles to `LoginView`, `UserModView`, … (`ViewMap`); content and language
  files keep their names. Projects referencing view classes (`login::getPath()`, `visits`) use step 14's paths.

### Step 17 – acronyms of the backend API

- `ID` → `id`, `userID` → `userId`, `getUserIDForBearerOrThrow()` → `…Id…` in properties, methods and argument names;
  properties readable under the old name for one release (deprecated property hook), renamed arguments are breaking.

### Step 18 – snake_case database

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
