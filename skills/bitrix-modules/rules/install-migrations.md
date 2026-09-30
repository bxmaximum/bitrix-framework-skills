# Install migrations (`install/migrations/`)

**Since main 26.600\***. Fallback: `rules/installer.md` → "Fallback before main 26.600". 31 kernel modules (`sale`, `catalog`, `rest`, `bizproc`, `bitrixcloud` …) install this way; most no longer ship `install/db/*.sql`.

`CModule::installMigrations(): Result` (protected) includes, if present, `install/migrations/tables.php` → `events.php` → `agents.php` of the module dir (`/local/modules/<id>` works as is). `uninstallMigrations(bool $dropTables): Result` re-runs `tables.php` (only when `$dropTables`) and `events.php` in reverse mode. A file may `return` a failed `Result` to stop.

| Call in the file | Install | Uninstall |
| --- | --- | --- |
| `table()->create()` | CREATE; existing table/index skipped | DROP TABLE (**data loss**) |
| `table()->alter()` | skipped (fresh install = final schema) | skipped |
| `event()->register()` / `registerCompatible()` | registers handler | unregisters it |
| `agent()->add()`, `option()->set()`, `stepper()->add()` | adds | nothing — `unRegisterModule()` removes agents; delete options yourself |

Errors: **Since main 26.800** a `Migration\Exception` comes back as an `Error` in the `Result`; on 26.600–26.750 it is thrown — also wrap the call in `try/catch (\Bitrix\Main\UpdateSystem\Migration\Exception $e)`.

## `tables.php` — one schema for MySQL and PostgreSQL

```php
<?php

use Bitrix\Main\UpdateSystem\Migration;
use Bitrix\Main\UpdateSystem\Migration\CreateTableBuilder;

Migration::getInstance()->table('b_vendor_module_post')->create(function (CreateTableBuilder $table) {
    $table->addId(); // INT NOT NULL AUTO_INCREMENT + PRIMARY KEY
    $columns = $table->addColumn();
    $columns->varchar('TITLE', 255)->notNull();
    $columns->char('ACTIVE', 1)->notNull()->default('Y');
    $columns->int('AUTHOR_ID')->notNull();
    $columns->datetime('CREATED_AT')->notNull();
    $columns->text('BODY');
    $table->addIndex('ix_b_vendor_module_post_author', ['AUTHOR_ID']);
});
```

- Columns: `int`/`tinyInt`/`smallInt`/`mediumInt`/`bigInt($name, ?$size)`, `varchar($name, $len)`, `char($name, $len)`, `text`/`mediumText`/`longText`, `date`, `datetime`, `boolean`, `decimal($name, $m, $e)`, `float`, `double`, `blob`, `varbinary($name, $len)`. Modifiers: `notNull()`, `default(?string)`, ints `autoincrement()`, `unsigned()`.
- Keys: `addPrimaryKey($col)`, `addPrimaryKeys([...])`, `addIndex`, `addUniqueIndex`, `addFulltextIndex($name, [...])`.
- Use `datetime`, never `timestamp` (unsupported on PostgreSQL). Name indexes `ix_<table>_<purpose>`, `ux_…` (unique), `ixf_…` (fulltext); PostgreSQL names are normalized to that form anyway.
- Schema change for already-installed copies of a `/local` module: no updater runs `alter()` — ship it as a project migration (`bitrix-sprint-migration`).

## `events.php`

```php
<?php

\Bitrix\Main\UpdateSystem\Migration::getInstance()->event()
    ->register('vendor.blog', 'PostCreated', [\Vendor\Module\Internals\Integration\Vendor\Blog\EventHandler\PostCreatedEventHandler::class, 'handle'])
    ->registerCompatible('main', 'OnAfterUserAdd', \Vendor\Module\Internals\Integration\Main\UserHandler::class, 'onAfterAdd');
```

- `register()` = `registerEventHandler()` (VERSION 2), `registerCompatible()` = `registerEventHandlerCompatible()` (VERSION 1). Legacy `ExecuteModuleEventEx` events pass raw args either way (`bitrix-events`).
- Handler: `[Class::class, 'method']` or class + method as 3rd/4th arg; order via 5th arg `$sort` (**Since main 26.650**; before: always 100).
- Handlers are matched on uninstall by the stored class string: when moving old handlers here, keep the exact `toClass` spelling used before.

## `agents.php` (options and steppers may go in any file)

```php
<?php

$migration = \Bitrix\Main\UpdateSystem\Migration::getInstance();
$migration->agent()->add([\Vendor\Module\Infrastructure\Agent\SyncAgent::class, 'run'], 3600, isPeriod: false, execDelay: 600);
$migration->option()->set('vendor.module', 'sync_enabled', 'Y');
```

- First run is delayed by `max(60, execDelay)` s; `execDelay` can only postpone.
- `option()->set()` and `stepper()->add()` run only if the module is registered **or** its default table exists. While `installMigrations()` runs the module is not registered yet, so add `install/migration_config.json`: `{"defaultTableName": "b_vendor_module_post"}` — without it these calls are **silently skipped** (kernel modules keep the file in the module root; `/local` modules must use `install/`).

## Separate DB connection — **Since main 26.800**

```php
protected function configureMigrationContext(\Bitrix\Main\UpdateSystem\Migration\Context $context): void
{
    $context->setConnection(\Bitrix\Main\Application::getConnection('stats')); // from .settings.php connections
}
```

`tables.php` DDL goes to that connection; handlers, agents and options stay in the main DB. All migration table operations run master-only. Older: create those tables yourself on `Application::getConnection('stats')` in `InstallDB()`.
