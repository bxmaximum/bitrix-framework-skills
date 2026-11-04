# Миграции установщика (`install/migrations/`)

**Since main 26.600\***. На старом ядре: раздел «До main 26.600» в `rules/installer.md`. Так устанавливается 31 модуль ядра (`sale`, `catalog`, `rest`, `bizproc`, `bitrixcloud` …); большинство уже не поставляют `install/db/*.sql`.

Защищённый `CModule::installMigrations(): Result` последовательно подключает существующие `install/migrations/tables.php` → `events.php` → `agents.php` из каталога модуля (`/local/modules/<id>` поддерживается). `uninstallMigrations(bool $dropTables): Result` повторяет `tables.php` (только при `$dropTables`) и `events.php` в обратном режиме. Файл может остановить процесс, вернув неуспешный `Result`.

| Вызов в файле | Установка | Удаление модуля |
| --- | --- | --- |
| `table()->create()` | CREATE; существующие таблица/индекс пропускаются | DROP TABLE (**потеря данных**) |
| `table()->alter()` | Пропускается: новая установка получает итоговую схему | Пропускается |
| `event()->register()` / `registerCompatible()` | Регистрирует обработчик | Удаляет регистрацию |
| `agent()->add()`, `option()->set()`, `stepper()->add()` | Добавляет | Ничего: `unRegisterModule()` удаляет агенты, настройки удаляй самостоятельно |

Ошибки: **Since main 26.800** `Migration\Exception` возвращается как `Error` в `Result`; на 26.600–26.750 выбрасывается — также оборачивай вызов в `try/catch (\Bitrix\Main\UpdateSystem\Migration\Exception $e)`.

## `tables.php`: одна схема для MySQL и PostgreSQL

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

- Столбцы: `int`/`tinyInt`/`smallInt`/`mediumInt`/`bigInt($name, ?$size)`, `varchar($name, $len)`, `char($name, $len)`, `text`/`mediumText`/`longText`, `date`, `datetime`, `boolean`, `decimal($name, $m, $e)`, `float`, `double`, `blob`, `varbinary($name, $len)`. Модификаторы: `notNull()`, `default(?string)`, для целых `autoincrement()`, `unsigned()`.
- Ключи: `addPrimaryKey($col)`, `addPrimaryKeys([...])`, `addIndex`, `addUniqueIndex`, `addFulltextIndex($name, [...])`.
- Используй `datetime`, не `timestamp` (не поддерживается в PostgreSQL). Имена индексов: `ix_<table>_<purpose>`, `ux_…` (уникальный), `ixf_…` (полнотекстовый); PostgreSQL всё равно нормализует имена к такому виду.
- Обновление схемы установленной копии `/local`-модуля: обновляющая система не запускает `alter()`. Поставляй изменение миграцией проекта (`bitrix-sprint-migration`).

## `events.php`

```php
<?php

\Bitrix\Main\UpdateSystem\Migration::getInstance()->event()
    ->register('vendor.blog', 'PostCreated', [\Vendor\Module\Internals\Integration\Vendor\Blog\EventHandler\PostCreatedEventHandler::class, 'handle'])
    ->registerCompatible('main', 'OnAfterUserAdd', \Vendor\Module\Internals\Integration\Main\UserHandler::class, 'onAfterAdd');
```

- `register()` соответствует `registerEventHandler()` (VERSION 2), `registerCompatible()` — `registerEventHandlerCompatible()` (VERSION 1). Старые события `ExecuteModuleEventEx` в обоих случаях передают исходные аргументы (`bitrix-events`).
- Обработчик: `[Class::class, 'method']` либо класс и метод третьим/четвёртым аргументами. Порядок задаёт пятый аргумент `$sort` (**Since main 26.650**; раньше всегда 100).
- При удалении обработчик сопоставляется с сохранённой строкой класса: при переносе старой регистрации сохраняй точное написание `toClass`.

## `agents.php` (настройки и steppers допустимы в любом файле)

```php
<?php

$migration = \Bitrix\Main\UpdateSystem\Migration::getInstance();
$migration->agent()->add([\Vendor\Module\Infrastructure\Agent\SyncAgent::class, 'run'], 3600, isPeriod: false, execDelay: 600);
$migration->option()->set('vendor.module', 'sync_enabled', 'Y');
```

- Первый запуск откладывается на `max(60, execDelay)` секунд; `execDelay` может лишь отложить его.
- `option()->set()` и `stepper()->add()` выполняются, только если модуль зарегистрирован **либо** существует его основная таблица. Во время `installMigrations()` регистрации ещё нет: добавь `install/migration_config.json` с `{"defaultTableName": "b_vendor_module_post"}`. Без него вызовы **молча пропускаются** (ядро держит файл в корне модуля; для `/local` нужен `install/`).

## Отдельное соединение БД (**Since main 26.800**)

```php
protected function configureMigrationContext(\Bitrix\Main\UpdateSystem\Migration\Context $context): void
{
    $context->setConnection(\Bitrix\Main\Application::getConnection('stats')); // из секции connections в .settings.php
}
```

DDL файла `tables.php` идёт в это соединение; обработчики, агенты и настройки остаются в основной БД. Все табличные операции миграций выполняются только на master. На старом ядре создавай таблицы через `Application::getConnection('stats')` в `InstallDB()`.
