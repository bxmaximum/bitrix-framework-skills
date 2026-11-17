---
name: bitrix-sprint-migration
description: 'Миграции проекта через sprint.migration: классы Version (up/down), HelperManager, генераторы и CLI migrate.php. Используй для переноса структуры, контента и настроек или проверки миграций.'
---

# sprint.migration

Baseline: main 23.0+ · Verified: sprint.migration 5.16.1, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

Модуль Marketplace/Composer `andreyryabin/sprint.migration` хранит изменения структуры и контента PHP-классами в VCS. Границу с установщиком модуля см. в `bitrix-modules`.

**Не установлен?** Проверь `/local/modules/sprint.migration/`, `/bitrix/modules/sprint.migration/`, затем `Loader::includeModule('sprint.migration')`. Если отсутствует, предложи установку Marketplace / Composer. Не создавай самодельную систему миграций.

| Задача | Читать |
| --- | --- |
| CLI, конфигурации, имена версий, консольный пользователь | `rules/cli-config.md` |
| `Version::up()`/`down()`, возобновляемые пакеты, файлы обмена | `rules/writing.md` |
| Помощники и генераторы, имена методов | `rules/helpers-builders.md` |
| Безопасность, ревью и проверка применения | `rules/checklist.md` |

## Значения по умолчанию, конфигурация `cfg`

| Параметр | По умолчанию |
| --- | --- |
| Каталог миграций | `{local\|bitrix}/php_interface/migrations`; local, если существует local/php_interface |
| Таблица версий | `sprint_migration_versions` |
| Класс | `namespace Sprint\Migration;` `Version<YmdHis>` наследует `Version` |
| CLI | `php {module}/tools/migrate.php <command>` |

## Общие правила

- Не меняй исходники модуля; записывай файлы миграций и конфигурации `migrations.*.php`.
- Создавай через `add` / генератор: ручные имена могут нарушить проверку времени.
- Находи сущности по `CODE`/`XML_ID`, не по ID другой БД.
- Применённую миграцию не меняй: исправление новой версией.
- `up`/`down` в общей / production БД — только с явным разрешением человека.
