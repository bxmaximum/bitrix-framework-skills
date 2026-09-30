---
name: bitrix-orm
description: 'D7 ORM — DataManager tablets, fields and relations, query()/ConditionTree, objects and collections, batch/merge/deleteByFilter writes, ORM events and cache. Use when designing entities or reading and writing data via ORM.'
---

# Bitrix D7 ORM

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| Tablet, field types, ArrayField, data modifiers, relations, UF | `rules/tablet-map.md` |
| `query()`, filters, runtime fields, DISTINCT, pagination | `rules/reading.md` |
| Collections: save, remove, relations, find/filter, keys | `rules/collections.md` |
| add/update/delete, batch, atomic increments, merge, deleteByFilter | `rules/writing.md` |
| ORM events, query cache, user input in queries | `rules/events-cache-security.md` |

## Invariants

- Namespace `Bitrix\Main\ORM\*`. `Bitrix\Main\Entity\*` is a legacy alias list (`main/include/compatibility.php`): don't write it in new code or mix both in one file.
- Build queries with `XxxTable::query()`, never `new Query(XxxTable::getEntity())`: `query()` honours the tablet's `getQueryClass()`.
- `add`/`update`/`delete`/`save()` run events and `cleanCache()`. `MergeTrait::merge()` and `deleteByFilter()` fire no events; raw SQL also leaves the cache stale.
- A collection has no `delete()`; `remove()` only detaches in memory.
- Never pass request data into `select`, `order`, `group`, `runtime` or `ExpressionField` without a whitelist.
