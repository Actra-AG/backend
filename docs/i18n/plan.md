# Plan: English base texts with selectable German (i18n)

Status: released as v1.1.0 (2026-10-04, commit 42e6f45); T8 dropped

## 1. Goal

All user-visible text of `actra/backend` (forms, views, HTML templates, tables, navigation, emails, enum labels) comes
from message classes instead of being hard-coded in German. English is the base text, German is a complete variant,
and projects choose the language (or override single texts) in `ActraBackendSettings`.

- **v1.1.0 (non-breaking):** message classes, `ActraBackendSettings::$messages`, **default German**. With the default
  settings the rendered HTML and the emails are identical to v1.0.0 (except that plain text is now always
  HTML-encoded, which changes nothing for the current texts). English is opt-in: `messages: BackendMessages::english()`.
- **v2.0.0 (breaking, separate task T8):** the default becomes English. Projects that want German pass
  `messages: BackendMessages::german()`.

Out of scope: data in the database (e.g. the group titles of `db/data.sql`), yuf's own texts outside of what yuf lets
us configure (yuf's `AuthResult::render()` is replaced by our own labels, see T5), exception messages for developers
(they stay English), a third language (the structure must allow it, nothing more).

## 2. Inventory (v1.0.0)

- ~170 German string fragments in 30 PHP files: forms (`src/libs/form/`), views (`src/view/backend/php/`, page titles,
  breadcrumbs, buttons, messages), tables (`src/libs/table/`, column labels, date formats), `AuthTokenTypeEnum::render()`,
  navigation titles in `ActraBackend.php`, `BackendView.php`, emails (`src/libs/email/`).
- 15 HTML content templates in `src/view/backend/html/` and the page templates in `src/view/backend/templates/`
  with German text.
- yuf texts used by the backend: `FormMessages` (forms), `AuthResult::render()` (visit log), `SmartTable::$noDataHtml`,
  `$totalAmountMessage_oneResult`, `$totalAmountMessage_numResults` (tables, public properties).
- The `lang` attribute of the page templates comes from yuf (`language` replacement = route language).

## 3. Architecture

Namespace `actra\backend\i18n`, directory `src/i18n/`. Same pattern as yuf's `FormMessages`:

```php
final readonly class AuthMessages
{
    public function __construct(
        public string $loginPageTitle = 'Log in',
        public string $loginIntro = 'Enter the email address you are registered with. ...',
        // ...
    ) {
    }

    public static function german(): AuthMessages
    {
        return new AuthMessages(
            loginPageTitle: 'Anmelden',
            loginIntro: 'Geben Sie nachfolgend die E-Mail-Adresse ein, ...',
            // ...
        );
    }
}
```

- **One message class per area** (one owner per class, see section 5): `CommonMessages` (shared texts),
  `LayoutMessages`, `AuthMessages`, `UserMessages`, `ProfileMessages`, `NotificationMessages`, `LogMessages`,
  `EmailMessages`. Constructor parameters are `public string` properties with the **English** text as default;
  `german()` returns the complete German variant. Projects override single texts with `with()`.
- **`BackendMessages`** (`final readonly`) bundles them: `languageCode` (`'en'`, `'de'`), `form` (yuf `FormMessages`),
  `common`, `layout`, `auth`, `user`, `profile`, `notification`, `log`, `email`. `BackendMessages::english()` and
  `BackendMessages::german()` (German uses `FormMessages::german()`). Custom texts:
  `BackendMessages::german()->with(auth: AuthMessages::german()->with(loginPageTitle: 'Login'))` (the properties are
  readonly, so `clone()` with properties only works inside the class: `OverridableMessages::with()`).
- **Placeholders** in square brackets like yuf (`[ipAddress]`, `[minutes]`, `[email]`), filled with
  `MessageTemplate::fill(template: $text, values: ['ipAddress' => $ip])`. The same placeholders must exist in the English
  and the German text (enforced by a test).
- **Plain text only.** Messages contain no HTML. They are always encoded: `HtmlText::unencoded(textContent: ...)` for yuf
  APIs that take `HtmlText`, `addHtmlText(..., HtmlText::unencoded(...))` for template replacements. Where markup is
  needed (links, `<strong>`), the view builds it around encoded text parts. Line breaks in emails are built in PHP.
- **Setting:** `ActraBackendSettings::__construct(..., ?BackendMessages $messages = null)`, property
  `public BackendMessages $messages` (`$messages ?? BackendMessages::german()` in 1.x).
- **Access:** forms, views, tables and emails read `ActraBackend::get()->actraBackendSettings->messages` (shortcut
  `ActraBackend::messages(): BackendMessages`). Constructor injection into the forms is a candidate for v2.0.0.
- **Forms:** `messages: ActraBackend::messages()->form` instead of `FormMessages::german()`.
- **Enum labels:** `AuthTokenTypeEnum::render()` and the visit status labels take their text from the messages
  (`LogMessages`), e.g. `public function render(LogMessages $messages): string` / a `match` in `LogMessages`.
- **Dates:** formats that differ per language (`d.m.Y`) are messages too (`CommonMessages::dateFormat`, ...).
- **HTML templates:** every German text in `src/view/backend/html/*.html` and `src/view/backend/templates/*.html` becomes
  `{tst:text value='identifier'}`; the view adds the identifier with `addHtmlText(identifier: ..., htmlText:
  HtmlText::unencoded(textContent: $messages->...))`. Identifiers are camelCase and unique per template.
- **`lang` attribute:** the page templates use `{tst:text value='backendLanguage'}` (added by `BackendView` from
  `BackendMessages::$languageCode`), so the attribute matches the texts, not the route.

## 4. Rules for all tasks

- Follow `AGENTS.md` and `docs/code-quality.md` (PHP 8.5, strict types, named arguments, `final`, full types, `=== null`).
- **German output must stay identical** to v1.0.0: copy the German texts verbatim (also typos and odd capitalisation
  like `weiter`/`Weiter`) into `german()`. Write natural, concise English for the defaults (backend UI style:
  "Save", "Cancel", "Email", "Log in", sentence case).
- Only edit the files your task owns (section 5). Need a text that another area owns or a shared text? Use it if it
  exists; otherwise add it to your own class and mention it in your report. Never edit `docs/i18n/plan.md`,
  `UPGRADE.md`, `README.md`, `composer.json`, `phpstan-baseline.neon` or files of other tasks.
- Do not commit, stage or push.
- Checks (DDEV, other sessions run in parallel in the same working tree):
  `ddev exec vendor/bin/phpstan analyse --no-progress --memory-limit=-1 <your files>` must report no errors for your
  files (an "ignored error pattern was not matched" for one of your files is expected after you fixed it; the
  orchestrator regenerates the baseline). `ddev composer test` must pass.
- Unit tests for your message class are covered by the generic `tests/Unit/i18n/MessagesTest.php` (all properties
  non-empty, same placeholders in English and German). Add specific tests for logic you add (e.g. enum label mapping).
- Report at the end: changed files, new message properties (name, English, German), texts you moved to a different
  area or added although they may be shared, open questions, check results.

## 5. Tasks

| Task | Owner | Depends on | Status |
|:--|:--|:--|:--|
| T0 Foundation | orchestrator | – | done |
| T1 Authentication | Sonnet session | T0 | done |
| T2 User management | Sonnet session | T0 | done |
| T3 Profile | Sonnet session | T0 | done |
| T4 Notifications | Sonnet session | T0 | done |
| T5 Visit and token logs | Sonnet session | T0 | done |
| T6 Layout, navigation and emails | Sonnet session | T0 | done |
| T7 Integration and release v1.1.0 | orchestrator | T1–T6 | done, smoke test open |
| T8 English default (v2.0.0) | later | T7 released | |

### T0 Foundation (orchestrator)

Files: `src/i18n/*` (all classes, the area classes as empty skeletons for T1–T6, `CommonMessages` complete),
`src/settings/ActraBackendSettings.php`, `src/ActraBackend.php` (only `messages()`), the shared components
`src/libs/form/AbstractSearchForm.php`, `src/libs/form/component/*`, `src/libs/table/AbstractTable.php`,
`tests/Unit/i18n/*`.

- `CommonMessages`: shared texts (save, cancel, send, search label and button, "all", "no changes", "email already in
  use", first name, last name, email, phone, IP whitelist label/info/invalid address, password texts used by several
  forms, date formats, table texts: no entries, one result, n results).
- `MessageTemplate::fill()`, `BackendMessages::english()`/`german()`, setting, `ActraBackend::messages()`.
- Shared components use `CommonMessages` (`SearchQueryField`, `IpWhitelistField` default info, `AbstractSearchForm`
  form messages, `AbstractTable` yuf table texts).
- Generic test `MessagesTest` over all message classes (reflection): every property non-empty in `english()` and
  `german()`, identical placeholder sets.

### T1 Authentication (Sonnet)

Owns `AuthMessages`; views and templates `login`, `loginPassword`, `loginPasswordToken`, `loginToken`, `logout`,
`passwordForgotten`, `passwordForgottenRes`, `passwordReset`, `passwordResetRes` (`src/view/backend/php/` and
`src/view/backend/html/`); forms `LoginForm`, `LoginPasswordForm`, `LoginTokenForm`, `PasswordForgottenForm`,
`PasswordResetForm`; user-visible texts in `src/libs/auth/MyAuthenticator.php` if any.

### T2 User management (Sonnet)

Owns `UserMessages`; views and templates `users`, `user`, `userAdd`, `userInvite`, `userMod`; forms `UserAddForm`,
`UserInviteForm`, `UserModForm`, `UserSearchForm`; `src/libs/table/UserTable.php`; user-visible texts in
`src/libs/auth/UserController.php` and `src/libs/db/DbAuthUser.php` if any (e.g. rendered values).

### T3 Profile (Sonnet)

Owns `ProfileMessages`; views and templates `profile`, `profileChangePassword`, `profileCreatePassword`,
`profileRemovePassword`; forms `ProfileForm`, `ProfilePasswordForm`.

### T4 Notifications (Sonnet)

Owns `NotificationMessages`; views and templates `notifications`, `notification`, `notificationSend`; form
`NotificationSendForm`; tables `NotificationTable`, `NotificationRecipientTable`; user-visible texts in
`src/libs/db/DbAuthUserNotification.php` if any.

### T5 Visit and token logs (Sonnet)

Owns `LogMessages`; views and templates `visits`, `tokens`; forms `VisitSearchForm`, `TokenSearchForm`; tables
`VisitTable`, `TokenTable`; `src/settings/AuthTokenTypeEnum.php` (label from `LogMessages`); visit status labels for
all `AuthResult` cases and the extra filter options ("Kein Zugriff", "Unbestätigter Zugang") from `LogMessages` instead
of yuf's `AuthResult::render()` (German texts identical to yuf's current ones).

### T6 Layout, navigation and emails (Sonnet)

Owns `LayoutMessages` and `EmailMessages`; `src/view/backend/templates/*.html`, `src/BackendView.php` (breadcrumb,
navigation, `backendLanguage` replacement), navigation titles in `src/ActraBackend.php` (not `messages()`),
`src/libs/common/OldNavigator.php` if it has texts, `src/libs/email/*` (subjects and bodies of `EmailLoginToken`,
`EmailPasswordResetLink`, `EmailAuthUser`, greeting and closing).

### T7 Integration and release v1.1.0 (orchestrator)

Review the reports and diffs (German output identical, English complete and consistent, no HTML in messages), resolve
duplicates into `CommonMessages`, regenerate the baseline (may only shrink), `composer check`, grep for remaining German
text, render check of the DB-free forms in both languages, `UPGRADE.md` (v1.1.0, feature, how to switch to English and
override texts, announcement of the English default in v2.0.0), `README.md` (configuration example), smoke test in a
consuming project if available, commit message and tag proposal.

### T8 English default (v2.0.0) – dropped

Replaced by section 6: the texts follow the language of the route, so there is no global default language to switch.

### T8 (old text)

`ActraBackendSettings`: `$messages ?? BackendMessages::english()`; `UPGRADE.md` breaking change with migration
(`messages: BackendMessages::german()`); optionally constructor injection of the messages into the forms.

## 6. Multilingual routes and per-user language (v1.1.0, decided 2026-10-04)

Decisions of the user:
- The backend can be registered under several routes, one per language. The texts of a request always follow the
  language of its route; the `lang` attribute comes from the route again (yuf's `language` replacement). A route never
  shows texts of another language. `BackendMessages::$languageCode` and the `backendLanguage` replacement (T6) are
  removed (never released).
- Default texts per route: `BackendMessages::forLanguageCode()`: `de` → `german()`, every other code → `english()`.
  Projects can pass own messages per route.
- Configuration is additive (non-breaking, v1.1.0): `ActraBackend::init(path:, isDefaultForLanguage:)` with
  `ActraBackendSettings::$language` stays the main route (`ActraBackendSettings::$messages` = its messages, default from
  its language). New `ActraBackendSettings::$additionalRoutes` (`list<BackendRoute>`), `BackendRoute(path, language,
  isDefaultForLanguage = false, ?BackendMessages messages = null)`.
- Every user has a language (`auth_user.language`, `NULL` = language of the main route). Emails to other users use
  the recipient's language where the backend generates text (welcome email default text and login link).
  Notifications contain only the text the admin typed, so their default text follows the sender's route.

### Architecture

- `ActraBackend` keeps the list of `BackendRoute`s (main route first; paths and language codes unique, else
  `LogicException`) and the current route. `BackendView` activates the route of the request
  (`ActraBackend::get()->activateRoute(route: RequestHandler::get()->route)`) before anything else. Without an
  activated route (CLI, init) the main route is current.
- `ActraBackend::messages()`: messages of the current route. New `ActraBackend::path()`: path of the current route;
  all links use it instead of `ActraBackend::get()->path` (that property stays: path of the main route).
  `ActraBackend::get()->getRouteForLanguage(languageCode: ...)`: route of a language (fallback: main route).
- Navigation: the items are created at init for the main route; `activateRoute()` updates their `href` and `title`
  for the current route.

### Tasks

| Task | Owner | Depends on | Status |
|:--|:--|:--|:--|
| R0 Routes foundation | orchestrator | – | done |
| R1 Per-user language | Sonnet session | R0 | done |
| R2 Integration, docs | orchestrator | R1 | done, smoke test open |
| R3 Redirect after login to the user's language route | orchestrator | R1 | done |
| R4 Navigation hook for project items | orchestrator | R0 | done |
| R5 Language switcher | Sonnet session | R0 | done |
| R6 Pagination titles | orchestrator | yuf v4.1.0 | done |
| R7 Final integration and release v1.1.0 | orchestrator | R3–R6 | done (smoke test skipped) |

**R0 (orchestrator):** `BackendRoute`, `BackendMessages::forLanguageCode()` (remove `languageCode`),
`ActraBackendSettings::$additionalRoutes`, route registration and validation, `activateRoute()`, `messages()`,
`path()`, `getRouteForLanguage()`, navigation update, `lang` back to the route, all links via `ActraBackend::path()`,
tests for the route logic.

**R1 (Sonnet):** `auth_user.language` (`db/updates/1.1.0.sql`, `db/schema.sql`), `DbAuthUser::$languageCode`
(`?string`), repository select/insert/update; language select in `UserAddForm`, `UserModForm` and `ProfileForm`
(options: the languages of the backend routes, labels from `Locale::getDisplayLanguage()` in the current language;
only shown when more than one language is configured; empty = main route language); language on the user detail
page; `UserInviteForm` default subject/text from the recipient's route messages and the login link to the recipient's
route path; messages for the new texts (English and German).

**R2 (orchestrator):** review, `composer check`, baseline, render check in two languages, `UPGRADE.md`/`README.md`
(multilingual configuration, database update 1.1.0), smoke test if possible.

## 7. Handover notes

(appended by the orchestrator after each task)

### T0 Foundation – done

- `src/i18n/`: `BackendMessages` (`english()`, `german()`, `languageCode`, `form` = yuf `FormMessages`),
  `CommonMessages` (complete, see the class for the shared texts), `MessageTemplate` (`fill()`,
  `listPlaceholderNames()`), empty skeletons `LayoutMessages`, `AuthMessages`, `UserMessages`, `ProfileMessages`,
  `NotificationMessages`, `LogMessages`, `EmailMessages`.
- `ActraBackendSettings::$messages` (constructor argument `?BackendMessages $messages = null`, default German),
  `ActraBackend::messages()`; `ActraBackend::get()` throws a `LogicException` before `init()`.
- `AbstractSearchForm` uses `ActraBackend::messages()->form`, `SearchQueryField` the common search label,
  `IpWhitelistField` the common info text (new optional argument `fieldInfo`), `AbstractTable` sets yuf's table texts
  (no entries, one/n results) from `CommonMessages` (German output unchanged).
- `CommonMessages::$newPasswordTooShort` has the placeholder `[minLength]` (fill with `'8'`).
- Tests: `tests/Unit/i18n/MessagesTest.php` (every class: non-empty, same placeholders, no HTML),
  `MessageTemplateTest`. Baseline 119 → 112 errors.

### T4 Notifications – done (review in T7)

- `NotificationMessages` filled (15 texts); views, templates, `NotificationSendForm`, both notification tables and
  `DbAuthUserNotification` use the messages. `sendInfo` takes the button text as `[send]`; `[firstName]`/`[lastName]`
  stay literal in the default notification text. Date columns use `CommonMessages::$dateTimeFormat`.
- Candidates for `CommonMessages`: sender, date, sent on, recipients.
- Open for T7: `DbAuthUserNotification` renders subject and message with `isEncodedForRendering: true` (existing
  behaviour, possible stored XSS by backend users) – check and fix.

### T3 Profile – done (review in T7)

- `ProfileMessages` filled (25 texts); profile views, `profile.html`, `ProfileForm` and `ProfilePasswordForm` use the
  messages. Minimum password length is `ProfilePasswordForm::MIN_PASSWORD_LENGTH` (filled into `[minLength]`).
- German HTML differs only in whitespace (two wrapped paragraphs are single lines now).
- Candidates for `CommonMessages`: current password texts, change/remove links, API key label, success prefix
  ("Erfolgreich:", also used by T4).

### T1 Authentication – done (review in T7)

- `AuthMessages` filled (29 texts); the nine auth views and templates and the five auth forms use the messages.
  `MyAuthenticator` has no user-visible texts.
- German HTML differs only in whitespace (wrapped intro paragraphs are single lines now).
- For T7: `MIN_PASSWORD_LENGTH = 8` is now a private constant in both `PasswordResetForm` (T1) and
  `ProfilePasswordForm` (T3) – merge into one place. Candidates for `CommonMessages`: password label/required.

### T6 Layout, navigation and emails – done (review in T7)

- `LayoutMessages` (10 texts) and `EmailMessages` (11 texts) filled; `default.html` (skip link, menu, session change,
  profile, logout, delete dialog), `BackendView::addLayoutTexts()` incl. `backendLanguage` for the `lang` attribute of
  both page templates, navigation title in `ActraBackend::init()`, login token and password reset emails. The login
  token intro is one complete sentence per case. `Mailer`, `OldNavigator`, `EmailAuthUser` have no texts.
- Behaviour change to review: `BackendView` throws an `UnauthorizedException` if the parent session or the first
  navigation item is missing (fatal error before).
- Candidates for `CommonMessages`: cancel, log out (also T1), delete dialog texts.

### T5 Visit and token logs – done (review in T7)

- `LogMessages` filled (34 texts, `authResult(AuthResult)` and `authTokenType(AuthTokenTypeEnum)` with `match` over all
  cases); visits/tokens views, both search forms and tables use the messages; own labels instead of yuf's
  `AuthResult::render()`. New `tests/Unit/i18n/LogMessagesTest.php` (German labels equal yuf's).
- For T7: `AuthTokenTypeEnum::render()` now requires a `LogMessages` argument – breaking for projects that call it;
  make the argument optional (default: backend messages) to keep v1.1.0 non-breaking.
- For T7: the "Created (client)" / "Redeemed (client)" values (browser data from the DB) are output unencoded
  (existing behaviour, XSS risk) – encode them.

### T2 User management – done

- `UserMessages` filled; user list/detail/add/edit/invite views and templates, the user forms, `UserTable` and
  `DbAuthUser` use the messages. `user.php` baseline errors fixed. (`hasApi` in `user.html` is set globally by
  `BackendView`, no bug.)

### T7 Integration – done (smoke test open)

- Consolidated into `CommonMessages`: success and note labels (punctuation inside the message), changes saved,
  remove link, API key texts (label, value label, none, generated, generate link, needs IP whitelist); the
  duplicates in `UserMessages`, `ProfileMessages`, `NotificationMessages` are removed.
- `BackendView::addTexts()` replaces the repeated replacement code (`user.php`, `profile.php`, layout texts).
- `NewPasswordCheck` (min. length 8, confirmation) replaces the duplicated checks of `PasswordResetForm` and
  `ProfilePasswordForm`; unit tested.
- `AuthTokenTypeEnum::render(?LogMessages $messages = null)` stays compatible.
- Security: notification detail values/labels and token client data are HTML-encoded.
- Overrides: readonly properties cannot be changed with `clone()` from outside the class, so the message classes got
  `with()` (`OverridableMessages` trait) and `BackendMessages::with()`; tested.
- Verified: German HTML of the DB-free forms (login, password login, token, password forgotten, password reset with
  errors, bad CSRF, array input) is byte-identical to v1.0.0; English renders completely in English (yuf texts
  included). Email texts compared with the v1.0.0 code. No German text left outside `src/i18n/`.
- `composer check` green (57 tests), baseline 119 → 91 errors.
- Open: smoke test of the DB-backed views and emails in a consuming project (none uses yuf 4 yet); yuf pagination
  titles stay German (yuf change needed).

### R0 Routes foundation – done

- `BackendRoute` (path, language, isDefaultForLanguage, messages default `BackendMessages::forLanguageCode()`),
  `BackendRouteCollection` (main route first, unique paths and languages, `findByPath()`, `getForLanguage()` with
  fallback to the main route, `listLanguageCodes()`), unit tested.
- `ActraBackendSettings::$additionalRoutes`; `$messages` default follows `$language`. `BackendMessages::$languageCode`
  removed, `BackendMessages::forLanguageCode()` added.
- `ActraBackend`: registers one yuf route per backend route, `$currentRoute`, `activateRoute(Route)` (called first in
  the `BackendView` constructor; unknown routes select the backend route of their language), `messages()` and new
  `path()` of the current route, `getRouteForLanguage()`, public `$backendRouteCollection`. The navigation item is
  re-added for the activated route (same key replaces it in place; yuf's `NavigationItem` is readonly).
- All 24 links use `ActraBackend::path()`; the page templates use yuf's route `language` for `lang` again.
- `composer.json`: `ext-intl` (language names via `Locale::getDisplayLanguage()`, already required by yuf).
- Checked at runtime: German main route `/backend/` and English `/en/backend/` – navigation, links and form texts
  follow the activated route.

### R1 Per-user language – done

- `auth_user.language` (`db/updates/1.1.0.sql`, `db/schema.sql`), `DbAuthUser::$languageCode`, repository
  insert/update; `DbRowReader` (typed reading of DB rows) fixes the legacy `mixed` errors of `DbAuthUserRepository`
  and `DbAuthSessionRepository`.
- `UserLanguageOptions` (languages of the routes, display names via intl in the current route's language, default
  text "Standard ([language])"), `LanguageField`; in `UserAddForm`, `UserModForm`, `ProfileForm` and on the user detail
  page only with several languages. `UserInviteForm` prefills subject/text in the recipient's language and links to
  the recipient's route.
- Known limitation: a stored language without route shows the default option; saving the form stores `NULL`.

### R2 Integration – done (smoke test open)

- Runtime check: language select in German (`/backend/`: "Standard (Deutsch)", "Englisch") and English
  (`/en/backend/`: "Default (German)", "English"); German HTML of the DB-free forms still byte-identical to v1.0.0.
- `UPGRADE.md` v1.1.0 and `README.md` ("Languages") describe routes, per-route texts, `with()`, user language and the
  database update; `docs/code-quality.md` got the route rules. Baseline 119 → 63 errors, 76 tests.
- Follow-ups (not planned): redirect a user after login to the route of their language; yuf pagination titles.

### Decisions 2026-10-04 (second round)

- A user without language (`NULL`, e.g. all users after the database update) has no preference and stays on the route
  of the login; only users with a chosen language are redirected after login.
- v1.1.0 waits for yuf v4.1.0 (configurable pagination titles) and then requires `actra/yuf` `^4.1`.
- Projects get a hook to translate their own navigation items per route (R4).
- A language switcher in the page header is part of v1.1.0 (R5).

### R3 Redirect after login – done

- `MyAuthUser::getFirstAllowedPage()` moves the target (requested page or first navigation item) to the route of the
  user's language (`BackendRouteCollection::findByLanguage()`, `translatePath()`: longest matching route path,
  query kept). No language or a language without route: no redirect. `MyAuthUser` baseline errors fixed (a user
  without allowed navigation item gets an `UnauthorizedException`).

### R4 Navigation hook – done

- `BackendNavigationInterface::addNavigationItems(NavigationItemCollection, BackendRoute)`, set with
  `ActraBackendSettings::$projectNavigation`; called by `init()` for the main route and by every `activateRoute()`
  (same navigation key replaces the item in place). Checked at runtime with a sample project item.

### R6 Pagination titles – waiting for yuf v4.1.0

- Prompt for the yuf session: `TablePaginationRenderer` gets optional `previousTitle`/`nextTitle` (defaults: current
  German texts), passed to `Pagination::render()`; v4.1.0.
- Then here: `composer.json` `actra/yuf` `^4.1`; texts in `CommonMessages` (`paginationPrevious`, `paginationNext`;
  German "Zurück"/"Vor"); the backend tables pass a `TablePaginationRenderer` with these texts (all tables extend
  `AbstractTable`; add a constructor there or pass it in each table); German HTML unchanged.

### R5 Language switcher – done

- `LanguageSwitcher` (pure, unit tested) and `LanguageSwitcherEntry`: one entry per route, link = current path
  without query string moved with `translatePath()`, label = language name in its own language; current language as
  `<span aria-current="true">`. `BackendView::addLanguageSwitcher()` adds `hasLanguageSwitcher`, `languageSwitcher`
  (`HtmlDataObjectCollection`); `LayoutMessages::$languageSwitcherLabel` ("Change language" / "Sprache wechseln").
- Both page templates (`default.html`: between website link and user menu; `authentication.html`: auth header, also
  on login pages), CSS block `_language-switcher.css`, `.auth-header` wraps. No output with one language.
- Not checked in a browser (layout on desktop and small screens) – part of the smoke test. 84 tests, baseline 60.

### R6 Pagination titles – done

- `actra/yuf` `^4.1` (v4.1.0: `TablePaginationRenderer(previousTitle:, nextTitle:)`, English defaults in yuf).
- `CommonMessages::$paginationPrevious` / `$paginationNext` ("Previous"/"Next", "Zurück"/"Vor");
  `AbstractTable::__construct()` passes them to the renderer, the five tables are unchanged. Checked at runtime:
  German route "Zurück / Vor", English route "Previous / Next". `composer check` green (84 tests).

### R7 Release v1.1.0 – done

- Smoke test in a consuming project skipped by decision of the user (2026-10-04); checks done: PHPStan level 10,
  84 unit tests, render comparisons of the DB-free forms (German byte-identical to v1.0.0, English complete) and
  runtime checks of routes, navigation, language select, project navigation hook and pagination titles.
- Not checked in a browser or with a database: DB-backed views, emails, login redirect, switcher layout.