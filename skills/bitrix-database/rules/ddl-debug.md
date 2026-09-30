# DDL and query debugging

## Where schema lives

- Module tables: `install/migrations/tables.php` run by `CModule::installMigrations()`, one file for MySQL and PostgreSQL (**Since main 26.600\***; see `bitrix-modules`).
- Older kernels: paired `install/db/mysql/install.sql` + `install/db/pgsql/install.sql`, or `XxxTable::getEntity()->createDbTable()` from the ORM map.
- Project schema changes over time: `sprint.migration` if installed (`bitrix-sprint-migration`).

## DDL builders (**Since main 26.600\***)

```php
use Bitrix\Main\DB\Ddl\Builder\CreateTableBuilder;

if (!$db->isTableExists('vendor_log'))
{
    $t = new CreateTableBuilder('vendor_log');
    $t->addId();                                    // INT autoincrement ID + primary key
    $t->addColumn()->varchar('CODE', 50)->notNull();
    $t->addColumn()->datetime('CREATED_AT')->notNull();
    $t->addIndex('ix_vendor_log_code', ['CODE']);   // positional args only
    $db->runDdl($t);                                // renderDdl($t) returns the SQL list
}
```

- `AlterTableBuilder`: `addColumn()`, `modifyColumn()`, `dropColumn()`, `renameColumn()`, `dropIndex()`, `addIndex()`; `DropTableBuilder` drops. All go through `runDdl()`.
- **Since main 26.800**: the index name parameter was renamed (`$mysqlIndexName` → `$indexName`), so named arguments break; builders return `static`, so a subclass overriding with `: self` is a Fatal. Don't extend builders.
- `datetime`, not `timestamp` (PostgreSQL has no MySQL `TIMESTAMP` semantics). PostgreSQL index names are unique per schema: include the table name.
- Fallback (main < 26.600): `$db->createTable()`, `$db->createIndex()`, `$db->isIndexExists()`, `$db->dropTable()`.

## SqlTracker

```php
$tracker = $db->startTracker(true);          // true = reset collected queries
$tracker->startFileLog('/var/log/bitrix/sql.log'); // optional: append each query with a backtrace
// ... code under investigation
$db->stopTracker();
$tracker->stopFileLog();

foreach ($tracker->getQueries() as $q)
{
    $slow[] = [$q->getTime(), $q->getSql()];
}
$total = $tracker->getTime();
$count = $tracker->getCounter();
```

- There is no `$tracker->stop()`: stop via `$db->stopTracker()`.
- Dev only. The kernel's `$DBDebugToFile` does the same into `/mysql_debug.sql`.
