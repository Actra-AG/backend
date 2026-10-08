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
  [yuf framework](https://github.com/Actra-AG/yuf) (`actra/yuf`). Its public API includes the database tables and the
  generated HTML, CSS and JavaScript (`standards/versioning.md`).
- Minimum PHP version: 8.5. Releases are Git tags with a section in `UPGRADE.md`.
- yuf is developed in parallel (local checkout usually at `../yuf`); raise it as described in
  `standards/versioning.md`, section 8.
- Ongoing goal: bring the backend to the current yuf and to the global standard, without project deviations (see
  [docs/standard-migration/plan.md](docs/standard-migration/plan.md)).

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

- Runtime dependencies: `actra/yuf` (which brings `actra/autoloader`), `ext-intl` and `ext-mbstring`.
- PHPStan and PHPUnit find the yuf classes as described in yuf's README, section "Static analysis and tests"
  (https://github.com/Actra-AG/yuf#static-analysis-and-tests).
- `.ddev/config.yaml` provides PHP 8.5 and MariaDB.
- Consuming project for browser checks (`standards/testing.md`): `../drogeriehaas.ch` with this checkout as Composer
  path repository (set up and adapted by the user).

### Forms (yuf form API)

- Follow the rules for forms in yuf's README (section "Rules for forms",
  https://github.com/Actra-AG/yuf/blob/main/README.md#rules-for-forms; in `vendor/actra/yuf/README.md` from yuf
  v4.57.2 on). The `FormMessages` of the request language are `ActraBackend::messages()->form`.

### Texts (`standards/i18n.md`)

- Message classes in `src/i18n/` (English default, German in `german()`), shared texts in `CommonMessages`; read with
  `ActraBackend::messages()`, output with `BackendView::addTexts()`. Placeholders `[name]` are filled with
  `MessageTemplate::fill()` and checked by `tests/Unit/i18n/MessagesTest.php`.
- One route per language: `ActraBackend::messages()` and `ActraBackend::path()` belong to the current route. Text for
  another user (e.g. an email) uses that user's route: `ActraBackend::get()->getRouteForLanguage()`.

### Releases

- `UPGRADE.md` sections up to v1.5.2 use the former format (split into "HTML & CSS (Frontend)" and "Backend & API")
  and stay as they are.

## Deviations from the global standard

- View classes found by `BackendViewFactory` (the views of the backend in `src/view/backend/php/` and the project views
  based on `BackendView`) have a lowercase class name equal to the file title (`login`, `userMod`), not PascalCase
  (`standards/naming.md`). Reason: the factory builds the class name from the requested file name, like yuf's
  `ClassNameViewFactory` (allowed by yuf's README, section "Views"). Applies only to these view classes.

Temporary, legacy code migrated step by step (see [docs/standard-migration/plan.md](docs/standard-migration/plan.md)).
New code follows the global standard; existing names are kept until their step is done, because renaming them breaks
consuming projects:

- Static accessors `ActraBackend::get()`, `messages()` and `path()` and static repositories instead of constructor
  injection; they move into `BackendViewContext` step by step (plan steps 14 and 15).
- Acronyms in capitals (`ID`, `$userID`) in names and database columns, camelCase database tables and columns.
- Interfaces with `Interface` suffix, classes that are not `final`.
