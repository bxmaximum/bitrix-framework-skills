# CLI и конфигурации

## Путь модуля

```bash
# из корня сайта
if [ -f local/modules/sprint.migration/tools/migrate.php ]; then
  MIGRATE=local/modules/sprint.migration/tools/migrate.php
else
  MIGRATE=bitrix/modules/sprint.migration/tools/migrate.php
fi
php "$MIGRATE" <command> [args] [options]
```

Только CLI SAPI; загрузка Битрикс через `prolog_before.php`, `DOCUMENT_ROOT` при пустом значении берётся из пути. Если файлы есть, а регистрация отсутствует, модуль установится. Symfony: `php bin/console sprint:migration` с зарегистрированным bundle.

## Команды

Полный список: `{module}/commands-en.txt`.

| Команда | Назначение |
| --- | --- |
| `add [desc] [name]` | Пустая миграция (`--desc=`, `--name=`) |
| `ls` | Список (`--new`, `--installed`, `--search=`, `--tag=`) |
| `up` | Применить все новые (`--search=`, `--tag=`, `--add-tag=`) |
| `up <version>` | Применить одну |
| `down <version>` | Откатить одну |
| `down` | **ОПАСНО:** откатывает все установленные, при необходимости с фильтром `--search=`/`--tag=` |
| `redo <version>` | down, затем up |
| `run <builder>` | Запустить генератор (`rules/helpers-builders.md`) |
| `mark <version\|new\|installed\|unknown> --as=installed\|new` | Сменить статус без выполнения кода |
| `delete <version\|new\|installed\|unknown>` | **ОПАСНО:** удаляет файлы / записи миграций |
| `config`, `--config=<name>` | Показать / переключить конфигурацию |

```bash
php "$MIGRATE" add "Добавить инфоблок новостей" NewsIblock
php "$MIGRATE" ls --new
php "$MIGRATE" up
php "$MIGRATE" down Version20260720113800   # всегда указывай версию
php "$MIGRATE" --config=shop up
```

## Имена версий

- Допустимое имя PHP-класса, совпадает с файлом, пространство `Sprint\Migration`.
- Содержит время в `version_timestamp_format`, по умолчанию YmdHis.
- `version_name_template = #NAME##TIMESTAMP#`: `Version20260720113800`, `NewsIblock20260720113800`.

## Конфигурации

Модуль читает `migrations.<name>.php` из `local/php_interface`, иначе `bitrix/php_interface`, и каталогов обработчиков `OnSearchConfigFiles`. Файл возвращает массив. `migrations.cfg.php` заменяет стандартную конфигурацию cfg.

`migration_dir` и `migration_table` **не выводятся** из `<name>`. Без них используется общий каталог / таблица cfg. Задавай оба:

```php
<?php
return [
    'title' => 'Миграции магазина',
    'migration_dir' => '/local/php_interface/migrations.shop', // от корня сайта, если нет migration_dir_absolute
    'migration_table' => 'sprint_migration_shop',
    // exchange_dir по умолчанию равен migration_dir
    // 'console_user' => 'admin' | 'login:deploy' | false,
    // 'version_builders' => [...], 'migration_extend_class' => 'Version',
];
```

Кнопка создания конфигурации записывает именно эти два ключа. Страница настроек только создаёт / удаляет файлы, значения в опциях не хранит.

## Консольный пользователь

- `console_user`, по умолчанию admin: первый активный пользователь группы администраторов; `login:<login>` — указанный пользователь; false — без авторизации.
- `console_auth_events_disable`, по умолчанию true: в CLI пропускает события авторизации.
- Административный интерфейс и CLI используют общие файлы / таблицу версий; автоматизация через CLI.
