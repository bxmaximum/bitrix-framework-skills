---
name: bitrix-highloadblock
description: 'Highload blocks — HighloadBlockTable, compileEntity data classes, HLBLOCK_{id} user fields, hlblock links, iblock directory property, ORM events, hl_element_* rights. Use when creating HL dictionaries or reading/writing their records.'
---

# Highload blocks

Baseline: main 23.0+ · Verified: highloadblock 25.100.0, main 26.800.0

Flat ORM tables whose columns are user fields (`UF_*`): no sections, tree or SEO. Need those → `bitrix-iblocks`; schema owned by code → own tablet (`bitrix-orm`).

| Task | Read |
| --- | --- |
| Create/rename/delete blocks, UF fields, storage layout, recompile, migrations | `rules/manage-blocks.md` |
| Record CRUD, multiple/file values, errors, idempotent import | `rules/records.md` |
| `hlblock` links, `_REF`/`_SINGLE`, iblock `directory` property | `rules/relations-directory.md` |
| ORM events (double dispatch), rights `hl_element_*` | `rules/events-rights.md` |
| `highloadblock.list` / `highloadblock.view` | `rules/components.md` |
| Batching, indexes, cache, SQL tracking, diagnostics | `rules/performance.md` |

## Invariants

- `Loader::includeModule('highloadblock')` first. Find blocks by stable `NAME`; numeric `ID`s differ per environment.
- `HighloadBlockTable::compileEntity($idOrNameOrRow)` once per request, then `$entity->getDataClass()`. It throws `SystemException` for an unknown block.
- Write only via the data class (`add`/`update`/`delete`), never raw SQL on the HL table or its multiple-value tables.
- `add()`/`update()` silently drop unknown keys: check codes with `$dataClass::getEntity()->hasField()`.
- Data-class calls never check rights; check `hl_element_*` yourself.
- After UF, `NAME` or `TABLE_NAME` changes, continue in a new request.
