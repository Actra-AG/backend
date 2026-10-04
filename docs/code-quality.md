# Code Quality and Testing

These rules apply to everyone working on `actra/backend` and to all new and changed code. Existing code that does not
meet them yet is brought up to this standard when it is changed.

## 1. Tooling

`actra/backend` depends on `actra/yuf` only (plus `ext-mbstring`) and keeps its dependencies minimal. Only two dev-only
tools are allowed (`require-dev`, never a runtime requirement for consumers of the library):

| Tool    | Purpose                   | Why not local code                                              |
|:--------|:--------------------------|:----------------------------------------------------------------|
| PHPStan | Static analysis, level 10 | A type checker cannot reasonably be written in-house            |
| PHPUnit | Unit tests                | De-facto standard, wide IDE support, no runtime footprint       |

No PHPStan extensions or plugins, no mocking libraries, no fixture/faker libraries, no code style tools. Test doubles
are small hand-written classes in `tests/`.

## 2. Commands

Composer scripts (require PHP 8.5 and the dev dependencies installed with `composer install`):

```bash
composer phpstan           # static analysis
composer phpstan:baseline  # regenerate phpstan-baseline.neon
composer test              # all tests
composer check             # phpstan + test
```

With DDEV (`.ddev/config.yaml` provides PHP 8.5), prefix the commands with `ddev`, e.g. `ddev composer check`.

Every task and every commit must end with a green `composer check`.

## 3. PHPStan

- `phpstan.neon` in the project root: `level: 10`, `phpVersion: 80500`, analysed paths `src/` and `tests/`. yuf has no
  Composer autoload configuration, so its sources are made known with `scanDirectories: vendor/actra/yuf/src`. Do not
  lower the level or add exclusions.
- **Baseline for legacy code:** existing errors go into `phpstan-baseline.neon`.
    - New files must not appear in the baseline. `tests/` never has baseline entries.
    - When you change an existing file, fix its baseline entries and regenerate the baseline. The baseline may only
      shrink.
    - `@phpstan-ignore` is only allowed with an identifier and a reason, e.g.
      `// @phpstan-ignore argument.type (PDO returns mixed, value validated above)`.

## 4. Coding Rules (PHP 8.5)

### 4.1 Structure

- **One class, one purpose.** The name says what it does. If you need "and" to describe it, split it.
- Small methods (rule of thumb: ≤ 20 lines). Flat nesting: early returns, no `else` after `return`.
- Separate pure logic from I/O. Logic classes (validation rules, parsing, calculations) do not access `$_GET`, `$_POST`,
  `$_SESSION`, `$_SERVER`, the file system, the database or the clock directly. They get their input as arguments and
  are unit tested. Forms, views and repositories are the I/O layer; keep them thin.
- Prefer composition over inheritance. Abstract base classes only for a real "is a" relation; interfaces for
  extension points that projects implement (e.g. `UserDeleteHandlerInterface`).
- Keep good existing patterns. New abstractions need a reason.

### 4.2 Types

- Every PHP file starts with the copyright header followed by `declare(strict_types=1);`:
  ```php
  <?php
  /**
   * @copyright Actra AG - https://www.actra.ch
   * @license   MIT
   */

  declare(strict_types=1);
  ```
  `tests/Unit/FileHeaderTest.php` enforces this for `src/` and `tests/`.
- `final` classes by default. `readonly` classes or properties for value objects. Non-final only for intended
  extension points.
- Fully typed properties, parameters, constants and return types. No `mixed` in own code. PHPDoc only for what PHP
  cannot express (`list<string>`, `array<string, string>`, `non-empty-string`).
- No untyped "options" arrays. Use small readonly value objects (like `ActraBackendSettings`) or named arguments.
- Nullable types are written as `?Type`.
- **Enums first.** Every fixed set of values (states, types, modes, results) is a backed enum, never string/int
  constants or magic strings. Behaviour of a value lives on the enum and uses `match`. New enums end with `Enum`
  (like `AuthTokenTypeEnum`).
- Narrow external `mixed` (request data, DB rows, JSON, session) right at the boundary, with explicit checks in one
  place, and throw a meaningful exception on invalid data.
- Use PHP 8.5 features where they make code clearer (pipe operator `|>`, `#[\NoDiscard]`, `clone()` with properties,
  property hooks, asymmetric visibility). Never just to show off.

### 4.3 Style

- Named arguments for all calls, as in the existing code. Exception: methods marked `@no-named-arguments` (e.g.
  PHPUnit's `assert*()`) are called with positional arguments.
- Refer to the own class by its name (`ActraBackend::get()`), not `self::` / `static::`, as in the existing code.
- Compare with `=== null` / `!== null`, never `is_null()`. Strict comparisons (`===`, `in_array(strict: true)`) only.
- No abbreviations in names (`$formField`, not `$ff`).
- Comments explain *why*, not *what*. Keep them short.
- No dead code, no commented-out code, no `TODO` without a linked task in `docs/`.
- Exceptions: throw specific SPL exceptions (`InvalidArgumentException`, `LogicException`, …) or the yuf exceptions
  with a message that tells the developer what is wrong and how to fix it.

### 4.4 Forms (yuf form API)

- Every form passes `messages: FormMessages::german()` to `Form::__construct()`, so the texts of yuf (cancel link,
  invalid input, …) stay German like the texts of the backend.
- Use the typed getters and setters of the fields (`getValueAsString()`, `getValues()`, `isChecked()`, …), never
  untyped values. Initial values go into the constructor (or `setInitialValue()` in a field subclass).
- `PasswordField` always gets the matching `PasswordPurposeEnum` (`CURRENT` for login and confirming the current
  password, `NEW` for setting a password).
- Field checks are typed rules (`StringRule`, `StringListRule`, …, per line with `addEachRule()`), not overrides of
  the field's validation. Error messages are `HtmlText`; user input in a message is always encoded.

### 4.5 Security

- All output is HTML-escaped by default. Unescaped output must be explicit (e.g. `HtmlText::encoded()` only for text
  that is already safe HTML).
- SQL only with bound parameters. Identifiers that cannot be bound are validated against a whitelist.
- Keep the existing security features (CSRF tokens, IP whitelists, login attempt limits, token confirmation, hashed
  API keys and passwords) working.

### 4.6 HTML output, CSS and JavaScript

- The backend works without JavaScript: views, forms and tables are valid, accessible HTML (labels, `aria-*` where
  needed). JavaScript in `src/assets/js/` is progressive enhancement only.
- JavaScript: vanilla ES modules, one module per purpose in `src/assets/js/modules/`, attached via `data-*` attributes
  or CSS classes, no inline `onclick`, no external libraries.
- CSS: plain CSS in `src/assets/css/` (entry `backend.css`, one file per block in `blocks/`), no preprocessor.
- Changes to generated HTML, CSS classes or assets are breaking changes for projects that customize them and must be
  listed in `UPGRADE.md` under "HTML & CSS (Frontend)".

## 5. Public API and Upgrades

- `actra/backend` is a public library: every public class, method, argument name (named arguments!), enum case,
  database table and generated output is API.
- Breaking changes are allowed, but:
    - no feature may be lost; if something is removed, its replacement is documented,
    - every breaking change is listed in `UPGRADE.md` with migration instructions,
    - breaking changes are released as a new major version.

## 6. Tests

### 6.1 Layout

```
tests/
  bootstrap.php   # Composer autoloader, actra/autoloader for the yuf classes
  Unit/           # pure logic, no I/O, fast
phpunit.xml
```

Namespace and directory mirror `src/`: `actra\backend\tests\Unit\libs\form\rule\…` in `tests/Unit/libs/form/rule/`
tests `actra\backend\libs\form\rule\…` (Composer `autoload-dev`). PHPUnit 13: data providers via `#[DataProvider]`
attributes, no annotations.

### 6.2 What to test

- **Unit (mandatory for every new or refactored logic class):** validation rules, custom fields (with
  `FormInput::fromArray()`), value objects, enums with behaviour.
- **Before refactoring existing code**, add characterization tests that capture its current behaviour and output, so
  lost features show up as failing tests.
- Code that needs a real database, session, mail server or HTTP request (forms with repositories, views) is kept thin
  and checked in a consuming project.
- Test names describe behaviour: `testRuleFailsForInvalidIpAddress()`.
- One assertion topic per test. Use data providers for tables of cases.

### 6.3 Definition of Done (every task)

1. `composer check` is green (PHPStan level 10 without new baseline entries, all tests pass).
2. New and refactored logic classes have unit tests.
3. Changed views, forms, tables and assets are checked in a consuming project.
4. `UPGRADE.md` lists every breaking change and database update; `README.md` is updated where needed.