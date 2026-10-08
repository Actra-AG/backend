# Plan: adopt the shared tooling of `actra/coding-standard`

Closed 2026-10-08: the remaining tasks continue in [../standard-migration/plan.md](../standard-migration/plan.md)
(step 0: code style and tests, step 11: empty baseline).

`actra/backend` uses `actra/coding-standard` (v1.1.1) as development dependency. Its rules replace the former
`docs/code-quality.md`; `AGENTS.md` only keeps the project-specific rules. The shared PHPStan and PHP-CS-Fixer
configurations are wired in, but the existing code does not meet them yet, so `composer check` is red until the tasks
below are done.

## State after the switch

- `phpstan.neon` includes `vendor/actra/coding-standard/config/phpstan.neon` (level 10, bleeding edge, strict rules,
  deprecation rules, PHPUnit extension, disallowed calls).
- `phpstan-baseline.neon` was regenerated with the new rules: 31 → 144 entries (150 errors), all in `src/`. Most
  frequent identifiers: `method.missingOverride` (95), `argument.type` (7), `missingType.iterableValue` (6),
  `return.type` (5), `offsetAccess.notFound` (5), `function.strict` / `disallowed.function` (`in_array()` without
  `strict`).
- The 11 errors in `tests/` are **not** in the baseline (`tests/` never has baseline entries), so `composer phpstan`
  fails until task 2 is done: `#[\Override]` on `setUp()` (`GeneratedApiKeyFlashTest`), `offsetAccess.notFound`
  (`LanguageSwitcherTest`, `UserLanguageOptionsTest`), dynamic call of `markTestSkipped()` (`DBTest`).
- `.php-cs-fixer.dist.php` checks `src/` and `tests/`. `composer cs` fails for all 127 files, mostly
  `blank_line_after_opening_tag` (127), `single_blank_line_at_eof` (126) and `trailing_comma_in_multiline` (113).
- `tests/Unit/FileHeaderTest.php` was removed: PHP-CS-Fixer (`header_comment`, `declare_strict_types`) enforces the
  file header now. The header gets a blank line after `<?php` (PER Coding Style).
- `.editorconfig` added: every file ends with a newline (the former rule "no final newline" is dropped).

## Tasks

1. **Code style (`style` commit, no other changes):** run `ddev composer cs:fix` and review the diff. Check the risky
   fixers by hand, they can change behaviour: `use_arrow_functions` (2 files), `no_trailing_whitespace_in_string`
   (1 file, may change generated text or HTML), `modifier_keywords`. Run all tests. Changed generated output goes into
   `UPGRADE.md`.
2. **Tests:** fix the 11 PHPStan errors in `tests/`. `composer check` must be green afterwards.
3. **Baseline:** shrink it area by area, starting with `#[\Override]` in `src/` (no behaviour change). The rule "the
   baseline may only shrink" applies again from now on.

## Handover notes
