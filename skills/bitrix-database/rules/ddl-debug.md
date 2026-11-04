# DDL и отладка запросов

## Где описывать схему

- Таблицы модуля: `install/migrations/tables.php`, вызываемый `CModule::installMigrations()`; один файл для MySQL и PostgreSQL (**Since main 26.600\***; `bitrix-modules`).
- На старом ядре: пара `install/db/mysql/install.sql` + `install/db/pgsql/install.sql` либо `XxxTable::getEntity()->createDbTable()` по карте ORM.
- Изменения схемы проекта во времени: установленный `sprint.migration` (`bitrix-sprint-migration`).

## Построители DDL (**Since main 26.600\***)

```php
use Bitrix\Main\DB\Ddl\Builder\CreateTableBuilder;

if (!$db->isTableExists('vendor_log'))
{
    $t = new CreateTableBuilder('vendor_log');
    $t->addId();                                    // INT с автоинкрементом и первичным ключом
    $t->addColumn()->varchar('CODE', 50)->notNull();
    $t->addColumn()->datetime('CREATED_AT')->notNull();
    $t->addIndex('ix_vendor_log_code', ['CODE']);   // только позиционные аргументы
    $db->runDdl($t);                                // renderDdl($t) возвращает список SQL
}
```

- `AlterTableBuilder`: `addColumn()`, `modifyColumn()`, `dropColumn()`, `renameColumn()`, `dropIndex()`, `addIndex()`; `DropTableBuilder` удаляет таблицу. Выполняй через `runDdl()`.
- **Since main 26.800** параметр имени индекса переименован (`$mysqlIndexName` → `$indexName`): именованные аргументы ломаются. Построители возвращают `static`, поэтому переопределение в подклассе с `: self` вызывает Fatal. Не наследуй построители.
- Используй `datetime`, а не `timestamp`: в PostgreSQL нет семантики MySQL `TIMESTAMP`. Имена индексов PostgreSQL уникальны внутри схемы: включай имя таблицы.
- До main 26.600: `$db->createTable()`, `$db->createIndex()`, `$db->isIndexExists()`, `$db->dropTable()`.

## SqlTracker

```php
$tracker = $db->startTracker(true);          // true = сбросить собранные запросы
$tracker->startFileLog('/var/log/bitrix/sql.log'); // необязательно: запросы со стеком вызовов
// ... исследуемый код
$db->stopTracker();
$tracker->stopFileLog();

foreach ($tracker->getQueries() as $q)
{
    $slow[] = [$q->getTime(), $q->getSql()];
}
$total = $tracker->getTime();
$count = $tracker->getCounter();
```

- Метода `$tracker->stop()` нет: останавливай через `$db->stopTracker()`.
- Только разработка. Ядерный `$DBDebugToFile` пишет то же в `/mysql_debug.sql`.
