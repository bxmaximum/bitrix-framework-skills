---
name: bitrix-highloadblock
description: 'Highload-блоки: HighloadBlockTable, compileEntity, справочники, поля UF, связи и права. Используй при создании HL-блока, чтении и записи данных, связи с инфоблоком и ORM-событиях.'
---

# Highload-блоки

Baseline: main 23.0+ · Verified: highloadblock 25.100.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

Плоские таблицы ORM со столбцами UF: без разделов, дерева и SEO. Для этого нужны `bitrix-iblocks`; для схемы, принадлежащей коду, — свой таблет (`bitrix-orm`).

| Задача | Читать |
| --- | --- |
| Создание, переименование, удаление блоков, UF, хранение, перекомпиляция, миграции | `rules/manage-blocks.md` |
| CRUD записей, множественные / файловые значения, ошибки, идемпотентный импорт | `rules/records.md` |
| Связи `hlblock`, `_REF`/`_SINGLE`, свойство инфоблока `directory` | `rules/relations-directory.md` |
| Двойные ORM-события, права `hl_element_*` | `rules/events-rights.md` |
| `highloadblock.list` / `highloadblock.view` | `rules/components.md` |
| Пакеты, индексы, кеш, отслеживание SQL, диагностика | `rules/performance.md` |

## Общие правила

- Сначала `Loader::includeModule('highloadblock')`. Находи блок по стабильному `NAME`: числовые ID различаются между средами.
- `HighloadBlockTable::compileEntity($idOrNameOrRow)` один раз за запрос, затем `$entity->getDataClass()`. Неизвестный блок вызывает `SystemException`.
- Записывай через класс данных (`add`/`update`/`delete`), без SQL в HL-таблицу и таблицы множественных значений.
- `add()`/`update()` молча отбрасывают неизвестные ключи: проверяй `$dataClass::getEntity()->hasField()`.
- Класс данных не проверяет права: проверяй `hl_element_*` сам.
- После изменения UF, `NAME`, `TABLE_NAME` продолжай в новом запросе.
