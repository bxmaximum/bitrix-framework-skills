# Components `highloadblock.list` / `highloadblock.view`

Read-only public output. No caching (no `CACHE_TYPE`/`CACHE_TIME`), always `select *`.

## `bitrix:highloadblock.list`

| Param | Behaviour |
| --- | --- |
| `BLOCK_ID`* | block ID |
| `ROWS_PER_PAGE` | default 0 = **all rows**, no `nav_object` |
| `PAGEN_ID` | default `page`; unique per list on one page |
| `FILTER_NAME` | name of a global array with an ORM filter; must match `^[A-Za-z_][A-Za-z0-9_]*$`, else ignored |
| `SORT_FIELD` / `SORT_ORDER` | field must be an existing UF code (else `ID`); order `ASC`/`DESC`, default `DESC` |
| `DETAIL_URL` | `#ID#`, `#BLOCK_ID#` |
| `CHECK_PERMISSIONS` | `Y` → see below |

- Legacy GET overrides: `sort_type` beats even an explicit `SORT_ORDER`; `sort_id` is used when `SORT_FIELD` is not a valid UF. Both are whitelisted, not injectable.
- Paging calls `allowAllRecords(true)`: any visitor can fetch the whole table with `?<PAGEN_ID>=page-all`. The total comes from running the full unpaged query. Do not use it on large blocks; write your own component with `count_total` and a hard limit (`bitrix-components`).
- `$arResult`: `rows` (fields with `SHOW_IN_LIST = Y` pre-rendered as HTML, values escaped — don't escape again; other fields raw), `fields`, `tableColumns`, `sort_id`, `sort_type`, `nav_object`.
- On block/rights errors it calls `ShowError()` and skips the template.

## `bitrix:highloadblock.view`

| Param | Behaviour |
| --- | --- |
| `BLOCK_ID`*, `ROW_ID`* | block, key value |
| `ROW_KEY` | default `ID`; used as `'=' . ROW_KEY` in the filter — never take it from the request (unknown field → exception) |
| `LIST_URL` | `#BLOCK_ID#` |
| `CHECK_PERMISSIONS` | `Y` → see below |

`$arResult`: `ERROR`, `row` (raw; `false` if missing; duplicates → first row), `fields` (`getUserFieldsWithReadyData`, render via `$USER_FIELD_MANAGER->getListView()`). On block/rights errors `row`/`fields` are absent but the template still runs with `ERROR`.

## Rights and HTTP status

`CHECK_PERMISSIONS = 'Y'` passes with **any** operation (admins always pass) and reports denial as "not found". Components set no HTTP status. For a real 404/403 check before including them: valid IDs → block exists (404) → read right (403, `rules/events-rights.md`) → record exists (404). Don't reveal existence before the rights check. `CHECK_PERMISSIONS` does not protect your own queries in templates or AJAX.

## Templates

- Customize by copying to `/local/templates/<site_template>/components/bitrix/highloadblock.list/<name>/`, pass `<name>` as the 2nd `IncludeComponent` argument.
- `bitrix:highloadblock.field.element` is the internal renderer of the `hlblock` UF type (`main.view`, `main.edit`, `main.edit_form`, `main.filter_html`, `main.admin_list_view_html`, `main.admin_list_edit_html`, `main.admin_settings`, `main.public_text`). Don't place it on pages; override its templates to change how the field renders.
