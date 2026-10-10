# Plan: yuf ~5.0.0 without workarounds

Status: done (2026-10-10, v2.3.0). Goal: raise `actra/yuf` to `~5.0.0` and `actra/coding-standard` to `^1.19.0`, and
remove the workarounds A1–A15 that yuf v5.0.0 fixes at their cause (yuf: `docs/plans/done/backend-root-causes/plan.md`).
One release; no backwards compatibility layers (coding standard v1.19.0).

## Steps

1. A1 autoload, A15, A3, A6, A14, A2: typed yuf APIs instead of own parsing.
2. A9, A7, A11, A10, A8: forms and tables (`HtmlText`, `TableMessages`, `AuthResultMessages`, `hasChanges()`, integer
   options, `exportCsv()`).
3. A5, A4, A13: login (nullable password, `verifyPassword()`/`precheck()`/`logInVerifiedUser()`, `loginPath:`).
   Characterization tests first.
4. A12 navigation provider; optional items (`randomFromAlphabet()`, `setMinLength()`, `EqualsFieldRule`,
   `CompactFieldRenderer`, `afterResponse()`).
5. Strict-types guard test (coding standard v1.18.0), `UPGRADE.md`, docs, browser check list.

## Handover notes

### Steps 1–5 (2026-10-10, v2.3.0)

- Characterization first: `LoginFlowTest` (password login, token request, token login against the database
  `test_backend`, every rejection with its logged result) was green before A4 and stayed green after it;
  `LoginReturnPathTest` pins the target after the login.
- A1 `scanDirectories`, the yuf path in the test bootstrap and the backend's own `addPath()` removed (Composer autoload).
  A15 `getStringList()` for rights and IP whitelist. A3 `PathVars::list()`. A6 `selectRowsFromDb()` (DB method removed).
  A14 `SecretTokenHash::tryFrom()`. A2 `getStringList()` / `getStruct()` in `SessionBreadcrumbTrail` and
  `GeneratedApiKeyFlash`.
- A9 `DetailDataObject` with `HtmlText`. A7 `BackendMessages::$table` / `$authResult` (yuf texts, English default).
  A11 own `hasChanges()` removed. A10 `addIntItem()`, `getValueAsInt()`, `get(Added|Removed)IntValues()`,
  `check(Int)OptionsFilter()`; the legacy visit filter options 6/9 removed. A8 own CSV export removed (`exportCsv()`).
- A5 `null` password, no `ACCESS_DO_PASSWORD_LOGIN`. A4 `verifyPassword()` in the password form,
  `MyAuthenticator::findTokenLoginUser()` with `precheck()` for token requests, `logAuthResult()` protected; the token
  login keeps yuf's `doLogin()` with `null` password (it is `verifyCredentials()` + login, no copy).
  A13 `loginPath:` / `LoginRedirect`; the login views carry `returnTo` to the token step (`keepReturnPath()`).
- A12 `ActraBackend::createNavigation()` as navigation provider, `BackendViewContext::getNavigation()`; `has()` is not
  needed (the collection is new per request).
- Optional: `randomFromAlphabet()` (API key ids, login tokens), `setMinLength()` + `EqualsFieldRule`
  (`NewPasswordRules`), `afterResponse()` for the mails. Not used: `CompactFieldRenderer` (it changes the HTML of the
  search forms; user decision 2026-10-10: generated HTML, CSS and JavaScript stay unchanged).
- Open for yuf: `verifyPassword()` answers state rejections (IP, inactive, out tried) without
  `Password::spendVerificationTime()`, so the response time tells that such an address exists; the backend spent the
  time for every rejection before. Prompt for yuf in the report of this session. `RouteCollection` has one login path
  for all language routes.
- Frontend review: no template or CSS file changed.
