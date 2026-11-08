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
- yuf is developed in parallel (local checkout usually at `../yuf`).
- Open for the frontend developer:
  [docs/plans/frontend-coding-standard/plan.md](docs/plans/frontend-coding-standard/plan.md).
- No skeleton project. No example app; views are checked in the consuming project (see "Dependencies and tooling").
- Changes of HTML templates and CSS need a frontend review (`AGENTS.md` of the coding standard, "Working on a
  task").

## Directory layout

- `src/view/backend/php/` – the pages of the backend (one class per page; served on the backend route of the project
  by `BackendViewFactory`, so a new page name is reserved there: add it to `docs/views.md`), `view/backend/html/`
  their HTML content templates (rendered with `HtmlDocument::useContentFile()`), `view/backend/templates/` the page
  templates, `view/backend/confirmation/` the template of `ConfirmationView`.
- `src/assets/` – default CSS and JavaScript; `dist/` their built files (`npm run build` after every change of
  `src/assets/`, committed with it, see `docs/assets.md`).

## Project-specific rules

### Dependencies and tooling

- Composer loads all classes; there is no `actra/autoloader` (PHPStan and PHPUnit need no paths, the test bootstrap
  only requires `vendor/autoload.php`).
- Tests that need the database use `tests/Double/TestDatabase.php` (database `test_backend` in DDEV, created from
  `db/schema.sql` and `db/data.sql` once per run; skipped without database) and `TestUsers` for fixtures.
- `tests/Unit/view/ViewRenderTest.php` renders every view in all four languages with
  `tests/Double/BackendPageRenderer.php` (like yuf's `Core`); add every new view there (a view that only redirects,
  like `loginShared`, gets its own test).
- Browser checks (`standards/testing.md`) in a consuming project that the user names, with this checkout as Composer
  path repository (set up and adapted by the user).

### Forms (yuf form API)

- Follow the rules for forms in `vendor/actra/yuf/docs/forms.md`, section "Rules for forms". The
  `FormMessages` of the request language are `$this->backendContext->messages->form`.

### Texts (`standards/i18n.md`)

- Message classes in `src/i18n/` (English default, German in `german()`), shared texts in `CommonMessages`; read from
  `BackendViewContext::$messages`, output with `BackendView::addTexts()`. Placeholders `[name]` are filled with
  `MessageTemplate::fill()` and checked by `tests/Unit/i18n/MessagesTest.php`.
- One path for all languages (yuf's `usesRequestLanguage`): `$language`, `$messages` and `$dateFormatter` of
  `BackendViewContext` belong to the language of the request. Text for another user (e.g. an email) uses that user's
  language: `$actraBackend->getMessages(languageCode:)`.
- Services reach classes through `BackendViewContext` (views, forms, tables) or as constructor arguments; outside a
  request through the `ActraBackend` instance.

### Releases

- The upgrade notes up to v1.5.2 (`docs/upgrade/v1.md`) use the former format (split into "HTML & CSS (Frontend)" and
  "Backend & API") and stay as they are.

## Deviations from the global standard

None. The lowercase view class names (`login`, `userMod`) follow `standards/naming.md`, section 2.
