# Tests of project code

yuf and the backend load through Composer's autoloader: PHPStan and the PHPUnit bootstrap need no paths for them
(yuf's [testing docs](https://github.com/Actra-AG/yuf/blob/main/docs/testing.md)).

Integration tests connect to a test database with yuf's `FrameworkDb` and use the repositories on that connection
(`BackendRepositories::fromDb()`), without `ActraBackend::create()`:

```php
abstract class DatabaseTestCase extends TestCase
{
    protected BackendRepositories $repositories;

    protected function setUp(): void
    {
        $db = new FrameworkDb(
            connectionParameters: DbConnectionParameters::forMysql(dbSettings: new DbSettings(
                hostName: 'db',
                databaseName: 'app_test',
                userName: 'db',
                password: 'db',
            )),
            queryLog: new DbQueryLogList(clock: new SystemClock()),
        );
        $db->beginTransaction();
        $this->repositories = BackendRepositories::fromDb(db: $db);
    }

    protected function tearDown(): void
    {
        $this->repositories->db()->rollBack();
    }
}
```

Everything the repositories write inside the test is rolled back. DDL statements (`CREATE`, `ALTER`, `DROP`,
`TRUNCATE`) cause an implicit commit in MariaDB/MySQL and end the transaction, so do not use them in these tests.

## Limits of the rolled-back transaction

The transaction only covers the repositories on the connection of the test, and only code that opens no transaction
itself:

- Code that opens its own transaction throws yuf's `LogicException` "A transaction is already active" on the test's
  connection: e.g. `DbAuthTokenRepository::createTokenWithinLimit()` (login codes with a send limit,
  `BackendLogin::requestCode()`), `UserController`, `UserModForm`, `PasswordResetForm`. yuf has no nested
  transactions (savepoints) on purpose: a nested `beginTransaction()` is a programming error.
- `ActraBackend::create(connectDb:)` uses the connection the closure returns: pass the test's connection
  (`static fn(): FrameworkDb => $db`) to see the backend's writes in the test, but the code above still opens its own
  transaction on it.

## Tests of the login and of views

Test the login on project pages (`BackendLogin`, `LoginForm`, `LoginTokenForm`) and other views that use the backend
against a separate test database, without a transaction around the test:

- A database only for the tests (e.g. `db_test`), created from the backend's `db/schema.sql` and `db/data.sql` (and the
  project's tables) before the test run; the writes of the tests stay in it until the next reset.
- Each test makes its data unique (e.g. a random email address per test, `bin2hex(random_bytes(4))`) or creates the
  users it needs, so the tests do not depend on each other or on their order.
- `ActraBackendSettings(tokenSendLimit: null)`: the send limit of login codes counts the codes of earlier tests and
  runs; turn it off in tests (the limit has its own unit tests in the backend).
- A `LoginRateLimit` with a `RateLimiter` directory per test (e.g. below `sys_get_temp_dir()`), removed in `tearDown()`.
- A `RecordingMailer` in `MailerSettings`: the sent login code is read from its `sentMails`.
- yuf's `ViewTestClient` ([testing docs](https://github.com/Actra-AG/yuf/blob/main/docs/testing.md#views)) runs the
  project's login pages with one session across requests (email page, then code page) and records redirects.
