# AGENTS.md

Persistent instructions for developers and AI assistants working in this repository.

## Global standard

This project follows the Actra coding standard, installed as development dependency `actra/coding-standard`
(https://github.com/Actra-AG/coding-standard).

- Read [vendor/actra/coding-standard/AGENTS.md](vendor/actra/coding-standard/AGENTS.md) and the standards linked
  there before working on this project. They are binding. If `vendor/` is missing, run `composer install` first.
- The rules below only **add** project-specific rules or state explicit deviations (with reason). They take precedence
  over the global standard where they conflict.

## Project context

- `actra/backend` is a public Composer library: a ready-to-use backend (user management, password and one-time token
  login via email, profile, API keys, IP whitelists, notifications, visit and token logs) for projects built on the
  [yuf framework](https://github.com/Actra-AG/yuf) (`actra/yuf`). Every public class, method, argument name, enum case,
  database table and generated output is API (see `standards/versioning.md`).
- Minimum PHP version: 8.5. Releases are Git tags (`vMAJOR.MINOR.PATCH`) with a section in `UPGRADE.md`.
- yuf is developed in parallel (local checkout usually at `../yuf`). Its `UPGRADE.md` describes every change of the yuf
  API; follow it when raising the yuf requirement. The yuf version range in `composer.json` must match the API used in
  `src/`.
- Ongoing goal: bring the existing code up to the shared PHP-CS-Fixer and PHPStan configuration (see
  [docs/coding-standard/plan.md](docs/coding-standard/plan.md)).

## Directory layout

- `src/` – library code, namespace `actra\backend\` (PSR-4 in `composer.json`; `ActraBackend.php` also registers the
  path with `actra/autoloader`).
    - `ActraBackend.php`, `BackendView.php` – entry point and base view.
    - `settings/` – settings value objects and enums.
    - `i18n/` – the message classes with all user-visible texts (English defaults, `german()` variant).
    - `libs/auth/`, `libs/db/`, `libs/email/`, `libs/common/` – authentication, repositories and records, mails.
    - `libs/form/` – the forms (yuf form API), `component/` custom fields, `rule/` custom rules.
    - `libs/table/` – the tables (yuf `DbResultTable`).
    - `view/backend/php/` – the views (one class per route), `view/backend/html/` their HTML content templates,
      `view/backend/templates/` the page templates.
    - `assets/` – default CSS (`css/backend.css`) and JavaScript (`js/backend.js`, ES modules in `js/modules/`) that
      projects publish or bundle themselves.
- `db/` – `schema.sql` and `data.sql` for new installations, `updates/<version>.sql` for upgrades.
- `tests/` – PHPUnit tests, `Unit/` only.
- `docs/` – plans and analyses (`docs/<topic>/`).

## Project-specific rules

### Dependencies and tooling

- Runtime dependencies: `actra/yuf` (which brings `actra/autoloader`), `ext-intl` and `ext-mbstring`. Development
  dependencies are `actra/coding-standard` and PHPUnit only: no mocking, fixture or faker libraries.
- yuf has no Composer autoload configuration: `tests/bootstrap.php` loads its classes with `actra/autoloader`, and
  `phpstan.neon` makes them known with `scanDirectories: vendor/actra/yuf/src`.
- `.ddev/config.yaml` provides PHP 8.5 and MariaDB. If DDEV is not running, ask the user to start it.
- There is no running app in this repository. Changes to views, forms, tables, templates or assets are checked in a
  consuming project (one that requires `actra/backend`), ideally with this checkout as Composer path repository.
- Code that needs a real database, session, mail server or HTTP request (forms with repositories, views) is kept thin
  and checked in a consuming project. Custom fields are unit tested with `FormInput::fromArray()`.

### Code

- Exceptions: specific SPL exceptions or the yuf exceptions.
- Settings are readonly value objects in `src/settings/` (like `ActraBackendSettings`), not "options" arrays.

### Forms (yuf form API)

- Every form passes `messages: FormMessages::german()` to `Form::__construct()`, so the texts of yuf (cancel link,
  invalid input, …) stay German like the texts of the backend.
- Use the typed getters and setters of the fields (`getValueAsString()`, `getValues()`, `isChecked()`, …), never
  untyped values. Initial values go into the constructor (or `setInitialValue()` in a field subclass).
- `PasswordField` always gets the matching `PasswordPurposeEnum` (`CURRENT` for login and confirming the current
  password, `NEW` for setting a password).
- Field checks are typed rules (`StringRule`, `StringListRule`, …, per line with `addEachRule()`), not overrides of
  the field's validation. Error messages are `HtmlText`; user input in a message is always encoded.

### Texts (i18n)

- No hard-coded user-visible text in views, templates, forms, tables or emails: every text is a property of a message
  class in `src/i18n/` (English default, German in `german()`), shared texts in `CommonMessages`. Read them with
  `ActraBackend::messages()`.
- Messages are plain text without HTML and are always encoded (`HtmlText::unencoded()`, `BackendView::addTexts()`).
  Markup is built around them; punctuation that follows a label (`Success:`) belongs into the message.
- Dynamic parts are `[placeholder]`s filled with `MessageTemplate::fill()`; the English and German text use the same
  placeholders (`tests/Unit/i18n/MessagesTest.php`).
- The backend can run under several routes, one per language. `ActraBackend::messages()` and `ActraBackend::path()`
  return the texts and the path of the current route: never cache texts or build links from a fixed path. Text for
  another user (e.g. an email) uses that user's route: `ActraBackend::get()->getRouteForLanguage()`.

### Security

- Keep the existing security features (CSRF tokens, IP whitelists, login attempt limits, token confirmation, hashed
  API keys and passwords) working.

### HTML output, CSS and JavaScript

- JavaScript in `src/assets/js/` is progressive enhancement only: vanilla ES modules, one module per purpose in
  `src/assets/js/modules/`, attached via `data-*` attributes or CSS classes, no external libraries.
- CSS: plain CSS in `src/assets/css/` (entry `backend.css`, one file per block in `blocks/`), no preprocessor.
- Changes to generated HTML, CSS classes or assets are breaking changes for projects that customize them; their
  `UPGRADE.md` entry says whether projects must rebuild or republish their JavaScript and CSS bundles.

### Releases

- Database changes come with `db/updates/<version>.sql` and an updated `db/schema.sql` (and `db/data.sql` if needed),
  and are listed in `UPGRADE.md`.
- Update `README.md` when installation, initialization or documented usage changes.
- Changes are prepared in `UPGRADE.md` in the format of `standards/versioning.md`. Sections up to v1.5.2 use the
  former format (split into "HTML & CSS (Frontend)" and "Backend & API") and stay as they are.

### Files

- Development files (`AGENTS.md`, `CLAUDE.md`, `docs/`, `tests/`, PHPStan, PHP-CS-Fixer and PHPUnit config,
  `.editorconfig`) are excluded from the Composer dist package with `export-ignore` in `.gitattributes`. Add new
  development files there as well.

## Deviations from the global standard

- None.
