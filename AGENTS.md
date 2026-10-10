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
- The migration to the current yuf and the global standard is complete (v2.0.0, follow-up done with
  v2.4.2); open for the frontend developer:
  [docs/plans/frontend-coding-standard/plan.md](docs/plans/frontend-coding-standard/plan.md).
- No skeleton project. No example app; views are checked in the consuming project (see "Dependencies and tooling").
- Changes of HTML templates and CSS need a frontend review (`AGENTS.md` of the coding standard, "Working on a
  task").

## Directory layout

- `src/` – library code, namespace `actra\backend\` (PSR-4 in `composer.json`; the lowercase view classes match their
  file names, so `composer dump-autoload --optimize --strict-psr` runs without warnings).
  - `ActraBackend.php`, `BackendView.php`, `ConfirmationView.php` – entry point, base view and base of project
    confirmation pages.
  - `settings/` – settings value objects and enums.
  - `i18n/` – the message classes with all user-visible texts (English defaults, `german()` variant).
  - `libs/auth/`, `libs/db/`, `libs/email/`, `libs/common/` – authentication, repositories and records, mails.
  - `libs/form/` – the forms (yuf form API), `component/` custom fields, `rule/` custom rules.
  - `libs/table/` – the tables (yuf `DbResultTable`).
  - `view/backend/php/` – the views (one class per route), `view/backend/html/` their HTML content templates,
    `view/backend/templates/` the page templates, `view/backend/confirmation/` the template of `ConfirmationView`.
  - `assets/` – default CSS (`css/backend.css`) and JavaScript (`js/backend.js`, ES modules in `js/modules/`) that
    projects publish or bundle themselves.
- `db/` – `schema.sql` and `data.sql` for new installations, `updates/<version>.sql` for upgrades.
- `tests/` – PHPUnit tests, `Unit/` only.
- `docs/` – user documentation (details of `README.md`), `docs/upgrade/v1.md` with the upgrade notes of v1, plans in
  `docs/plans/`.

## Project-specific rules

### Dependencies and tooling

- Runtime dependencies: `actra/yuf` (`~6.1.0`), `ext-intl` and `ext-mbstring`. Composer loads all classes; there is
  no `actra/autoloader` (PHPStan and PHPUnit need no paths, the test bootstrap only requires `vendor/autoload.php`).
- Tests that need the database use `tests/Double/TestDatabase.php` (database `test_backend` in DDEV, created from
  `db/schema.sql` and `db/data.sql` once per run; skipped without database) and `TestUsers` for fixtures.
- `tests/Unit/view/ViewRenderTest.php` renders every view in both languages with `tests/Double/BackendPageRenderer.php`
  (like yuf's `Core`); add every new view there.
- `.ddev/config.yaml` provides PHP 8.5 and MariaDB.
- Consuming project for browser checks (`standards/testing.md`): `../drogeriehaas.ch` with this checkout as Composer
  path repository (set up and adapted by the user).

### Forms (yuf form API)

- Follow the rules for forms in `vendor/actra/yuf/docs/forms.md`, section "Rules for forms". The
  `FormMessages` of the request language are `$this->backendContext->messages->form`.

### Texts (`standards/i18n.md`)

- Message classes in `src/i18n/` (English default, German in `german()`), shared texts in `CommonMessages`; read from
  `BackendViewContext::$messages`, output with `BackendView::addTexts()`. Placeholders `[name]` are filled with
  `MessageTemplate::fill()` and checked by `tests/Unit/i18n/MessagesTest.php`.
- One route per language: `$messages`, `$route` and `$paths` of `BackendViewContext` belong to the route of the request.
  Text for another user (e.g. an email) uses that user's route: `$actraBackend->getRouteForLanguage()`.
- No static state: services reach classes through `BackendViewContext` (views, forms, tables) or as constructor
  arguments; outside a request through the `ActraBackend` instance.

### Releases

- The upgrade notes up to v1.5.2 (`docs/upgrade/v1.md`) use the former format (split into "HTML & CSS (Frontend)" and
  "Backend & API") and stay as they are.

## Deviations from the global standard

- View classes found by `BackendViewFactory` (the views of the backend in `src/view/backend/php/` and the project views
  based on `BackendView`) have a lowercase class name equal to the file title (`login`, `userMod`), not PascalCase
  (`standards/naming.md`). Reason: the factory builds the class name from the requested file name, like yuf's
  `ClassNameViewFactory` (allowed by `vendor/actra/yuf/docs/views.md`, section "Views"). Applies only to these view
  classes.
