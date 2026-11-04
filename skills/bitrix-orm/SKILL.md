---
name: bitrix-orm
description: 'Используй для D7 ORM Битрикса: DataManager, query(), объекты и коллекции, связи и пакетная запись. Правила событий, кеша и безопасной работы с данными. SQL без ORM — bitrix-database.'
---

# D7 ORM Битрикса

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

| Задача | Читать |
| --- | --- |
| Таблет, типы полей, ArrayField, преобразователи, связи, UF | `rules/tablet-map.md` |
| `query()`, фильтры, вычисляемые поля, DISTINCT, страницы | `rules/reading.md` |
| Коллекции: сохранение, удаление из состава, связи, find/filter, ключи | `rules/collections.md` |
| add/update/delete, пакеты, атомарное увеличение, merge, deleteByFilter | `rules/writing.md` |
| События ORM, кеш запросов, пользовательский ввод | `rules/events-cache-security.md` |

## Обязательные правила

- Пространство имён `Bitrix\Main\ORM\*`. `Bitrix\Main\Entity\*` — устаревший список псевдонимов (`main/include/compatibility.php`): не используй его в новом коде и не смешивай оба в файле.
- Строй запрос через `XxxTable::query()`, не `new Query(XxxTable::getEntity())`: `query()` учитывает `getQueryClass()` таблета.
- `add`/`update`/`delete`/`save()` вызывают события и `cleanCache()`. `MergeTrait::merge()` и `deleteByFilter()` не вызывают события; прямой SQL также оставляет кеш устаревшим.
- У коллекции нет `delete()`; `remove()` меняет только её состав в памяти.
- Данные запроса не должны попадать в `select`, `order`, `group`, `runtime`, `ExpressionField` без разрешённого списка.
