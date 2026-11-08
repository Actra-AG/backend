# Security audit of actra/backend and actra/yuf (2026-10)

Review of the code, templates, JavaScript, SQL, seed data, dependencies and git history of `actra/backend` and
`actra/yuf`, with tests of the findings. No backdoor and no malicious dependency was found; the fixes were released
in yuf v6.0.0 and backend v2.7.0, v2.7.1 changed two of them after review. What changed for consumers is in the
`UPGRADE.md` of both packages; the behaviour is documented in [docs/users.md](../../../users.md).

## Decisions

1. yuf first, then the backend, each as one release.
2. Microsoft login identifies users by object ID and tenant ID; yuf handles the nonce itself.
3. The wrong-password lock stays permanent until a new password is set; a successful login does not reset the counter.
   Counting is atomic.
4. Security defaults of yuf are on without opt-out switches, except where a legitimate use needs one.
5. Absolute URLs keep using the Host header (projects serve several domains); yuf checks it against `allowedDomains`.
6. Raw-HTML parameters of tables and navigation are typed `HtmlText`.
7. The seed user of `db/data.sql` has a reserved example address that receives no mail; installers change it to
   their own.
8. API keys authenticate a user (`ActraBackend::authenticateBearerOrThrow()`) and check the user's state, IP whitelist
   and `hasApi`.
9. One-time tokens are stored as hash only. Login codes keep 6 characters (bound to the session); reset links have 22
   letters and digits, short enough for one line in text mails.
10. The send limit of tokens stays per user, counted atomically.
11. `manage_users` is the right of full control (v2.7.1): no delegated user managers. Nobody deactivates or deletes their
    own account, the last user manager keeps the right.
12. Impersonation acts with one click; its links carry the CSRF token of the session (v2.7.1).
13. Logout stays a GET link (the login pages log out by design, the session cookie is `SameSite=Strict`).
14. No GitHub security advisories: the packages are used almost only by Actra projects, which are upgraded directly.

## Handover notes

- 2026-10-11: released as yuf v6.0.0 (v6.0.1, v6.1.0 follow-ups) and backend v2.7.0 to v2.7.2. Regression tests cover
  the fixes (`ApiKeyAuthenticationTest`, `DbAuthTokenRepositoryTest`, `PasswordResetFormTest`, `AccessControlTest`).
- 2026-11-08: findings, test scripts and project names removed from this file (coding standard: public repositories
  name no projects and give no hints at weaknesses of old versions).
