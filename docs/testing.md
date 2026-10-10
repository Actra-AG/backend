# Integration tests

yuf and the backend load through Composer's autoloader: PHPStan and the PHPUnit bootstrap need no paths for them
(yuf's [testing docs](https://github.com/Actra-AG/yuf/blob/main/docs/testing.md)).

Integration tests connect to a test database with `DB::fromSettings()` and use the repositories on that connection
(`BackendRepositories::fromDb()`), without `ActraBackend::init()`:

```php
abstract class DatabaseTestCase extends TestCase
{
    protected BackendRepositories $repositories;

    protected function setUp(): void
    {
        $db = DB::fromSettings(dbSettings: new DbSettings(
            hostName: 'db',
            databaseName: 'app_test',
            userName: 'db',
            password: 'db',
        ));
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
