# Wrong login codes per IP address

Goal: wrong login codes (`BackendLogin::logInWithCode()`) are limited per client IP address and logged, like login
attempts. Today they are counted only in the browser session (`AuthTokens::claim()`, `maxAllowedLoginAttempts`), not
by `LoginRateLimit`, and not written to the login log. Requirement of a consuming project: 30 wrong codes per IP and
hour.

## Decisions (2026-11-09)

- The setting and the counting belong in yuf: `LoginRateLimit` holds all login limits, and yuf's `Authenticator`
  writes the log (`log()` is private). The backend could count with the public `RateLimiter` itself, but that would
  copy yuf's `RateLimitGate` (fail mode, logging): a local workaround (coding standard, "Fix problems at their cause").
- Every code attempt is counted before the check: `RateLimiter` has no way to give a hit back, and counting first
  is race-free. A right code costs one hit; a normal login needs one or two of 30.
- Separate key `login|code|ip|<ip bucket>`: wrong codes do not use up the code requests and the other way round.
- `LoginCodeResultEnum::RATE_LIMITED` as new case (⚠️), like `LoginCodeRequestResultEnum::RATE_LIMITED` and
  `PasswordLoginResultEnum::RATE_LIMITED`; a second method to tell reasons apart would be less clear.

## Step 1: `actra/yuf` (prompt for a session in the yuf checkout)

```text
Goal: projects can limit and log wrong one-time codes (second step of a login, e.g. a code sent by email) per client
IP address, with the LoginRateLimit they already use. Today yuf leaves wrong codes to the project
(docs/session-and-login.md, "Wrong codes belong to the project") and the project cannot write the login log
(Authenticator::log() is private). Use case: actra/backend counts wrong codes only per browser session; a consuming
project needs "30 wrong codes per IP and hour" and the attempts in the login log.

API:
1. LoginRateLimit: new argument `codeLimits` (list<RateLimit>, after accountLimits), default
   [new RateLimit(maxHits: 30, windowSeconds: 3600)], at least one (InvalidArgumentException like the other lists).
   New method `consumeCode(string $ipAddress): RateLimitResult`: counts one code attempt per client IP address via
   RateLimitGate (clientKeyPrefix 'login|code|ip|', clientLimits: $this->codeLimits, limitsByKey: []), separate from
   the login counter, so code attempts and logins do not use up each other's budget; failMode applies.
2. AuthResultEnum: new case ERROR_WRONG_CODE = 16 (or ERROR_WRONG_TOKEN; pick the name that fits AuthMethodEnum::OTP
   and the docs), label() and AuthResultMessages::$errorWrongCode in all four languages (de 'Falscher Code',
   en 'Wrong code', fr 'Code erroné', it 'Codice errato').
3. Authenticator, two protected methods for a project's code step (both log through log(), so no logAuthResult() call
   in the project):
   - `consumeCodeAttempt(string $userName, ?int $userId): bool`: call before checking the code. Without
     LoginRateLimit (null) true. Otherwise LoginRateLimit::consumeCode() with the remote address; blocked: sets
     $authResult = ERROR_RATE_LIMITED and $retryAfterSeconds, logs the attempt with $userName / $userId, returns false.
     Allowed: authResult stays UNDEFINED (so logInVerifiedUser() can follow), $retryAfterSeconds 0.
   - `rejectWrongCode(string $userName, ?int $userId): void`: sets $authResult = ERROR_WRONG_CODE and logs it. The code
     itself is never passed or logged.
   Both call assertNoLoginTried() first (one attempt per authenticator).
   $userName: what the project knows of the pending user (e.g. the email address), '' if none.
4. docs/session-and-login.md: replace "Wrong codes belong to the project ..." with the new methods (count before the
   check with consumeCodeAttempt(), log a wrong code with rejectWrongCode(); counting wrong codes per user with
   registerWrongPasswordAttempt() stays possible) and update the finishTwoFactorLogin() example. docs/rate-limiting.md:
   table row of LoginRateLimit (code attempts per IP, default 30 per hour).
5. UPGRADE.md (minor release): new codeLimits default limits code attempts that were unlimited before, but only where
   the project calls consumeCodeAttempt(); ⚠️ new AuthResultEnum case breaks exhaustive match expressions on it in
   projects (backend: none, it uses label() and cases()). A project storing the enum as int (auth_logins.auth_result)
   needs no schema change.

Tests (LoginRateLimitTest, AuthenticatorTest with a test double): consumeCode() blocks the 31st attempt of one IP within
the window, another IP is unaffected, the window resets (Clock), does not touch login|ip| or login|account| counters
and vice versa; empty codeLimits throws; failMode CLOSED blocks / OPEN allows on a storage error; consumeCodeAttempt()
without LoginRateLimit returns true and logs nothing; blocked: logs ERROR_RATE_LIMITED with user name and ID, sets
retryAfterSeconds; rejectWrongCode() logs ERROR_WRONG_CODE; after an allowed consumeCodeAttempt(),
logInVerifiedUser() still works; a second call throws LogicException. label() covers the new case in all languages.
```

## Step 2: `actra/backend` (after the yuf release)

- Raise `actra/yuf` to the new minor version.
- `MyAuthenticator::tokenLogin()`: read the user ID of the session's code (`AuthTokens`, a new method that returns it
  without claiming; `null` without code) and the user's email (one query, `''` if unknown); `consumeCodeAttempt()`
  before `AuthTokens::claim()`; blocked: return without checking the code (also when it is right); `claim()` `null`:
  `rejectWrongCode()`. Known and unknown sessions are treated the same.
- `BackendLogin::logInWithCode()`: `ERROR_RATE_LIMITED` → `LoginCodeResultEnum::RATE_LIMITED` (new case, ⚠️).
- `LoginTokenForm::process()`: `RATE_LIMITED` shows `AuthMessages::$tooManyAttempts` (as the email step).
- Tests (`BackendLoginTest`, `LoginFlowTest`): 30 code attempts of one IP across several sessions and codes are
  checked, the 31st is refused with `RATE_LIMITED` without check, also when it is right; another IP is not affected;
  the window resets; a wrong code is logged with the user of the session's code, never the code; a blocked attempt is
  logged as `ERROR_RATE_LIMITED`; the session limit of 5 wrong codes is unchanged; with `loginRateLimit: null`
  nothing is counted, wrong codes are still logged.
- Docs: `docs/users.md` "Login rate limit" (replace "The login with a code that was sent is not counted again"),
  `ActraBackendSettings` doc comment of `$loginRateLimit`, `docs/login.md` if it lists the results.
- `UPGRADE.md`: requires the new yuf; wrong codes limited per IP (default 30 per hour, `codeLimits:` of
  `LoginRateLimit`), logged on the page "Visits"; ⚠️ `LoginCodeResultEnum::RATE_LIMITED`.
- Then the consuming project raises its constraint and sets `codeLimits:` if it needs other limits.

## Handover notes

- 2026-11-09: decisions and the yuf prompt written; nothing implemented. Next: step 1 in the yuf checkout.
- 2026-11-09: step 1 released as yuf v8.19.0 (`ERROR_WRONG_CODE`, `consumeCodeAttempt()` throws on a second call per
  authenticator). Step 2 done for backend v5.11.0, together with the login by code only. A wrong code is logged also
  without `loginRateLimit:`. An expired or used code and a session without code count as wrong codes. The session
  limit of wrong codes stays covered by `AuthTokensTest`. Not verified: the browser check in a consuming project.
  Plan finished.
