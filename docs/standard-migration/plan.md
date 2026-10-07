# Plan: migrate `actra/backend` to the global coding standard

Goal: the same standard in all Actra projects, without project deviations. `docs/coding-standard/plan.md` covers the
tooling (code style, PHPStan baseline); this plan covers the code and API that differ from the global standard.

Every phase is one or more small releases. Breaking changes are allowed in minor versions (see
`standards/versioning.md`), but no feature may be lost: each breaking change gets an `UPGRADE.md` entry with
before/after example. Prefer deprecating first where the old API can be kept with reasonable effort.

## Differences (state at v1.6.0)

| #  | Global rule                                                     | `actra/backend` today                                                                     | Breaking | Depends on yuf |
|:---|:----------------------------------------------------------------|:------------------------------------------------------------------------------------------|:---------|:---------------|
| 1  | Never `isset()` (`php.md` 5)                                    | 2× in `libs/common/OldNavigator.php`                                                      | no       | no             |
| 2  | `final` by default, `@internal` for non-API classes             | ~69 classes not `final` (repositories, records, tables, views, `MyAuthUser`, …)            | yes      | no             |
| 3  | Interfaces without `Interface` suffix                           | `UserDeleteHandlerInterface`, `BackendNavigationInterface`                                | yes      | no             |
| 4  | Settings bundles end with `Model`                               | `ActraBackendSettings`, `MailerSettings`                                                  | yes      | no             |
| 5  | Database tables and columns in snake_case                       | `auth_ipWhitelist`, 64 columns in camelCase (`authUserID`, `firstName`, `ipAddress`, `ID`) | yes      | no             |
| 6  | Acronyms like normal words (`$userId`)                          | `ID`, `$userID`, `$sessionID`, `$groupID`, … in properties and argument names              | yes      | partly         |
| 7  | Dependencies through the constructor, no new static state       | `ActraBackend::get()/messages()/path()` (67 files), static repositories (48 methods)      | yes      | yes            |
| 8  | Class names in PascalCase                                       | 28 view classes named like their route (`login`, `visits`, `userMod`)                     | yes      | yes            |

Findings for the dependencies on yuf:

- yuf does not access the tables of the backend; #5 is backend-only.
- yuf uses the same acronym style (`$userID`, `$sessionID`, `$authSessionID`, …), and `MyAuthUser` /
  `MyAuthenticator` / `BackendView` extend yuf classes. Names inherited from yuf are renamed after yuf.
- `ContentHandler::getViewClass()` creates a view with `new $phpClassName()` (no constructor arguments), and the class
  name is derived from the route file name. Constructor injection into views (#7) and PascalCase view classes (#8)
  need a yuf change first (e.g. a view factory / container and a mapping from route to class name).

## Tasks

Order: non-breaking first, then backend-only breaking changes, then the changes that follow yuf. Each task ends with
a green `composer check`, an `UPGRADE.md` entry and handover notes below.

1. **`OldNavigator` (#1):** check whether `OldNavigator` is still needed (legacy breadcrumb). Remove it, or replace
   `isset()` with `array_key_exists()` and typed reads through `HttpRequest`. Not breaking unless it is public API.
2. **Public API inventory (#2):** list every class, and decide per class: extension point (stays open, documented in
   README), API used by projects (`final`), or internal (`final` + `@internal`). Then make the classes `final` in one
   release. Check README and consuming projects for subclasses of backend classes first.
3. **Interfaces (#3):** add `UserDeleteHandler` and `BackendNavigation`; keep the old names as deprecated interfaces
   extending the new ones; accept the new types everywhere. Remove the old names in a later minor version.
4. **Settings (#4):** rename to `ActraBackendSettingsModel` and `MailerSettingsModel`. The old classes stay as
   deprecated subclasses for one release (they are `readonly`, not `final`). Update README examples.
5. **Database (#5):** rename `auth_ipWhitelist` → `auth_ip_whitelist` and all camelCase columns to snake_case
   (`authUserID` → `auth_user_id`, `ID` → `id`), with `db/updates/<version>.sql` (`RENAME TABLE`,
   `ALTER TABLE … RENAME COLUMN`, MariaDB ≥ 10.5 / MySQL ≥ 8.0), updated `db/schema.sql` and `db/data.sql`. Adapt all
   repositories (`DbRow` column names). The PHP property names stay unchanged in this task. Release alone, so projects
   that query the tables directly have one clear migration step. Characterization tests of the row mapping first.
6. **Acronyms, backend-only (#6):** rename `ID` → `id`, `userID` → `userId`, … in own classes, properties and argument
   names (named arguments are API). Where possible keep the old property readable for one release (property hook with
   `@deprecated`); renamed arguments cannot be kept and are listed in `UPGRADE.md`. Names inherited from yuf wait for
   task 8.
7. **Repositories without static methods (#7, backend part):** turn the static repositories into instances with a
   `DB` dependency, created in one place (`ActraBackend`). Keep the static methods as deprecated wrappers for one
   release. Pure logic gets its dependencies through the constructor and becomes unit testable.
8. **Follow yuf (#6, #7, #8):** coordinate with the yuf plans. After yuf has renamed its acronyms, supports a view
   factory with constructor injection and a mapping from route to PascalCase class name: rename the inherited names,
   inject `BackendMessages`, the route path and the repositories into the views, replace `ActraBackend::get()`,
   `messages()` and `path()` by injected dependencies, and rename the view classes (`login` → `LoginView`, …). Plan the
   yuf side in yuf's `docs/` first.

While a difference is not migrated yet, it is listed as temporary deviation in `AGENTS.md`. Remove the line when its
task is done.

## Handover notes
