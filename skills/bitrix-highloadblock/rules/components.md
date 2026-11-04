# Компоненты `highloadblock.list` / `highloadblock.view`

Публичный вывод только для чтения. Кеша нет (`CACHE_TYPE`/`CACHE_TIME` не поддерживаются), всегда `select *`.

## `bitrix:highloadblock.list`

| Параметр | Поведение |
| --- | --- |
| `BLOCK_ID`* | ID блока |
| `ROWS_PER_PAGE` | По умолчанию 0: **все строки**, без `nav_object` |
| `PAGEN_ID` | По умолчанию `page`; уникальный для каждого списка на странице |
| `FILTER_NAME` | Имя глобального массива ORM-фильтра; только `^[A-Za-z_][A-Za-z0-9_]*$`, иначе игнорируется |
| `SORT_FIELD` / `SORT_ORDER` | Код существующего UF, иначе `ID`; `ASC`/`DESC`, по умолчанию `DESC` |
| `DETAIL_URL` | `#ID#`, `#BLOCK_ID#` |
| `CHECK_PERMISSIONS` | `Y`: см. ниже |

- Старые GET-параметры: `sort_type` перекрывает даже явный `SORT_ORDER`; `sort_id` используется, если `SORT_FIELD` не код UF. Оба проверяются по разрешённым значениям, инъекции нет.
- Пагинация включает `allowAllRecords(true)`: любой посетитель выгрузит всю таблицу через `?<PAGEN_ID>=page-all`. Количество получается полным запросом без лимита. Для больших блоков нужен свой компонент с `count_total` и жёстким лимитом (`bitrix-components`).
- `$arResult`: `rows` (поля `SHOW_IN_LIST = Y` уже HTML с экранированием, повторно не экранируй; остальные сырые), `fields`, `tableColumns`, `sort_id`, `sort_type`, `nav_object`.
- При ошибке блока / прав вызывает `ShowError()`, шаблон не выполняется.

## `bitrix:highloadblock.view`

| Параметр | Поведение |
| --- | --- |
| `BLOCK_ID`*, `ROW_ID`* | Блок и значение ключа |
| `ROW_KEY` | По умолчанию `ID`; используется как `'=' . ROW_KEY` в фильтре. Не бери из запроса: неизвестное поле вызывает исключение |
| `LIST_URL` | `#BLOCK_ID#` |
| `CHECK_PERMISSIONS` | `Y`: см. ниже |

`$arResult`: `ERROR`, `row` (сырые данные, `false` без записи, при дублях первая), `fields` (`getUserFieldsWithReadyData`, вывод через `$USER_FIELD_MANAGER->getListView()`). При ошибке блока / прав `row`/`fields` отсутствуют, но шаблон выполняется с `ERROR`.

## Права и статус HTTP

`CHECK_PERMISSIONS = 'Y'` пропускает с **любой** операцией (администратор всегда), отказ показывает как «не найдено». HTTP-статус компоненты не меняют. Для 404/403 проверь до подключения: корректные ID → блок существует (404) → право чтения (403, `rules/events-rights.md`) → запись существует (404). До проверки прав не раскрывай наличие записи. `CHECK_PERMISSIONS` не защищает свои запросы шаблона / AJAX.

## Шаблоны

- Копируй в `/local/templates/<site_template>/components/bitrix/highloadblock.list/<name>/`, передавай `<name>` вторым аргументом `IncludeComponent`.
- `bitrix:highloadblock.field.element` — внутренний вывод типа UF `hlblock`: `main.view`, `main.edit`, `main.edit_form`, `main.filter_html`, `main.admin_list_view_html`, `main.admin_list_edit_html`, `main.admin_settings`, `main.public_text`. Не размещай на странице; для изменения вывода переопредели шаблоны.
