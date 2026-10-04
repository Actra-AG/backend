# AGENTS.md

Persistent instructions for developers and AI assistants working in this repository.

## Project context

- `actra/backend` is a public Composer library: a ready-to-use backend (user management, password and one-time token
  login via email, profile, API keys, IP whitelists, notifications, visit and token logs) for projects built on the
  [yuf framework](https://github.com/Actra-AG/yuf) (`actra/yuf`).
- Versioning is done with Git tags (`vMAJOR.MINOR.PATCH`, SemVer, stable since `v1.0.0`). Breaking changes require a
  new major version, features a minor version, fixes a patch version.
- Runtime dependencies: `actra/yuf` (which brings `actra/autoloader`) and `ext-mbstring`. Do not add Composer packages
  without asking.
- yuf is developed in parallel (local checkout usually at `../yuf`). Its `UPGRADE.md` describes every breaking change of
  the yuf API; follow it when raising the yuf requirement. The yuf version range in `composer.json` must match the API
  used in `src/`.

## Directory layout

- `src/` – library code, namespace `actra\backend\` (PSR-4 in `composer.json`; `ActraBackend.php` also registers the
  path with `actra/autoloader`).
    - `ActraBackend.php`, `BackendView.php` – entry point and base view.
    - `settings/` – settings value objects and enums.
    - `libs/auth/`, `libs/db/`, `libs/email/`, `libs/common/` – authentication, repositories and records, mails.
    - `libs/form/` – the forms (yuf form API), `component/` custom fields, `rule/` custom rules.
    - `libs/table/` – the tables (yuf `DbResultTable`).
    - `view/backend/php/` – the views (one class per route), `view/backend/html/` their HTML content templates,
      `view/backend/templates/` the page templates.
    - `assets/` – default CSS (`css/backend.css`) and JavaScript (`js/backend.js`, ES modules in `js/modules/`) that
      projects publish or bundle themselves.
- `db/` – `schema.sql` and `data.sql` for new installations, `updates/<version>.sql` for upgrades.
- `tests/` – PHPUnit tests (see `docs/code-quality.md`).
- `docs/` – conventions ([docs/code-quality.md](docs/code-quality.md)) and plans.

## Code quality

- Follow [docs/code-quality.md](docs/code-quality.md). It is binding for all new and changed code.
- Key rules: `declare(strict_types=1);` and the copyright header in every PHP file, `final` by default, fully typed,
  no `mixed` in own code, enums for every fixed set of values, named arguments, one purpose per class, pure logic
  separated from I/O.
- Leave every file you touch cleaner than you found it, but keep each change focused on one topic. Do not reformat
  unrelated code.
- Run `composer check` before finishing a task (see `docs/code-quality.md`, section 2); it must be green. The
  PHPStan baseline may only shrink.
- Without local PHP 8.5, run PHP and Composer commands through DDEV (`ddev composer check`). If DDEV is not running,
  start it with `ddev start` or ask the user to do it.
- There is no running app in this repository. Changes to views, forms, tables, templates or assets are checked in a
  consuming project (one that requires `actra/backend`), ideally with this checkout as Composer path repository.

## Response style

- Be concise. No filler text, no introductory or concluding pleasantries.
- Do not summarize or restate the problem unless asked.
- Mention assumptions when relevant.
- Do not mention the attached context unless it is needed for the answer.

## Files

- Do not add a final newline at the end of newly created files.
- `.gitignore` whitelists tracked files. New top-level files or directories must be added there, otherwise they are not
  committed.
- Development files (`AGENTS.md`, `CLAUDE.md`, `docs/`, `tests/`, PHPStan and PHPUnit config) are excluded from the
  Composer dist package with `export-ignore` in `.gitattributes`. Add new development files there as well.

## Git & commits

- Never run `git commit`, `git add` or `git push` on your own. Prepare the commit message and let the user commit.
- Inspect the actual changes (`git status`, `git diff`, `git diff --staged`) before proposing a commit message.
- Commit messages follow the existing history (Conventional Commits): `type(scope): summary`, `!` for breaking
  changes, then an empty line, a `- ` bullet list of the changes and an optional `Attention:` paragraph. Without the
  empty line, Git treats the whole message as subject.

## Releases

- Every release gets an entry in `UPGRADE.md`: changes of the generated HTML, CSS and JavaScript under
  "HTML & CSS (Frontend)", everything else under "Backend & API", newest first, as `### vX.Y.Z – <Month D, YYYY>`.
- Entries are prefixed with **Feature:**, **Logic Change:**, **Security:**, **Database:**, **Migration:** or
  **Breaking Change:**. Every breaking change needs migration instructions for consuming projects.
- Database changes come with `db/updates/<version>.sql` and an updated `db/schema.sql` (and `db/data.sql` if needed).
- Update `README.md` when installation, initialization or documented usage changes.

## Before commit suggestions

When asked to review changes before commit, inspect the changed files and answer:

1. Read `README.md` and say whether it needs to be updated.
2. Read `UPGRADE.md` and say whether it needs to be updated (always for breaking changes and database updates).
3. Suggest a commit message following the style of previous commit messages.
4. Check the existing Git tags (`git tag --sort=-v:refname`) and suggest the next release tag (SemVer: breaking change
   → major, feature → minor, fix → patch).