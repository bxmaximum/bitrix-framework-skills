# REST 3.0

**Since rest 26.200\***: транспорт и контроллеры V3 отсутствуют в rest 25.300. Для старого ядра используй классический аналог `/rest/…`. Реализация: `rest/lib/V3/`, маршруты `api/#aplogin#/#ap#/#method#`, `api/#method#` компонента `bitrix:rest.server`.

## Область применения

- Только перенесённые методы. Документация на 30.10.2026 включает задачи, базу знаний 2.0, почту, оргструктуру `humanresources.*` (`department.*` остаётся классическим), журнал, рабочее время, итоги звонков; в октябре добавлены CRM-формы `crm.form.*`, каталог/склад `catalog.*`, форум и `iblock.element.*`. У инфоблока должен быть включён доступ REST.
- Одинаковое имя бывает в двух версиях с разными параметрами/ответом, например `tasks.task.get`. Версию смотри в заголовке официальной страницы.
- `rest.documentation.openapi` (псевдоним `documentation`) отдаёт OpenAPI 3.0 конкретного портала: методы в `paths`, схемы в `components.schemas`. В коробке состав зависит от модулей и флагов.
- `rest.scope.list` (псевдоним `scopes`, только V3) сопоставляет методы и scopes.

## Вызов

- Вебхук: `POST https://{host}/rest/api/{userId}/{code}/{method}`. Приложение: `POST https://{host}/rest/api/{method}`, `auth` в JSON.
- Тело только JSON (`Content-Type: application/json`); form-data даёт `…INVALIDJSONEXCEPTION`. Query string не используется для параметров. Методы без параметров также допускают GET.
- Без `/api/` вызывается классический одноимённый метод, если он существует. Имя только V3 без `/api/` даёт `ERROR_METHOD_NOT_FOUND`.
- b24jssdk: `$b24.actions.v3.*`. Для SDK без подтверждённой поддержки нужного V3-метода используй прямой HTTP (`rules/sdk.md`).

## Формат запроса

```json
{
  "select": ["id", "title", "responsible.name"],
  "filter": [["status", "=", "NEW"], {"logic": "or", "conditions": [["id", [1, 2]], ["id", "in", [3, 4]]]}],
  "order": {"id": "ASC"},
  "pagination": {"limit": 50, "page": 1}
}
```

- Связанные поля в `select`: `responsible.name`. Неизвестное связанное поле даёт `…UNKNOWNDTOPROPERTYEXCEPTION`; неизвестное собственное поле молча исключается из ответа. Проверяй результат.
- Условия одного уровня объединяются AND. Операторы: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `between`. Фильтруются только поля с `filterable: true` из `*.field.list`, сортируются с `sortable: true`.
- `pagination`: `limit` по умолчанию 50, максимум 1000 (`PaginationStructure`), плюс `offset` либо `page`.

## Ответ и ошибки

- Успех: `result.item` (один), `result.items` (список) либо `result.result` (bool), плюс `time`.
- Ошибка: HTTP 4xx и `{"error": {"code", "message", "validation": [{"field", "message"}]}}`. Коды `BITRIX_REST_V3_EXCEPTION_*` следуют классу исключения: `…VALIDATION_REQUESTVALIDATIONEXCEPTION`, `…ACCESSDENIEDEXCEPTION` (403), `…INSUFFICIENTSCOPEEXCEPTION` (403), `…ENTITYNOTFOUNDEXCEPTION`, `…UNKNOWNFILTEROPERATOREXCEPTION`, `…METHODNOTFOUNDEXCEPTION`. Тарифный отказ может сохранить `FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN`.
- Язык сообщения: заголовок `X-Bitrix-Rest-Response-Language`, читается ядром, хотя не описан в проверенной документации.

## Пакетные вызовы REST 3.0

Тело — JSON-массив в корне; классический `cmd` даёт `…INVALIDSELECTEXCEPTION`.

```json
[
  {"method": "tasks.task.get", "query": {"id": 101, "select": ["id"]}, "as": "first"},
  {"method": "tasks.task.update", "query": {"id": {"$ref": "first.id"}, "fields": {"title": "Updated"}}},
  {"method": "tasks.task.list", "query": {"select": ["id"], "filter": [["id", ">", 100]]}, "as": "found"},
  {"method": "tasks.task.list", "query": {"select": ["title"], "filter": [["id", "in", {"$refArray": "found.id"}]]}}
]
```

- `$ref` берёт значение `name.field`/`index.field` без обёртки `item`; `$refArray` собирает поле строк списка. Имена `as` уникальны. Значения из ссылок приводятся к типу целевого поля, включая вложенные поля и фильтр `in`; явно переданные значения не приводятся. Неприводимый тип даёт `…VALIDATION_REQUESTVALIDATIONEXCEPTION` (400).
- Выполнение последовательное, без вложенного batch и общей транзакции. Ошибка даёт верхнеуровневый `error` без результатов, хотя предыдущие команды уже могли записать данные.

## Защита от повторов Idempotency-Key

**Since rest 26.600.** На старой версии перед повтором создания ищи объект по собственному внешнему ключу.

- Заголовок `Idempotency-Key: <1–255 печатных ASCII-символов>` (например UUID) для add/update/delete. Повтор с тем же ключом и телом в течение 24 часов возвращает сохранённый ответ и `Idempotent-Replayed: true`, без повторного действия.
- Область ключа: приложение/вебхук + пользователь + метод. Тело сравнивается побайтно через sha256: повторяй исходные байты, не пересобранный JSON.
- Сохраняются только ответы 200; ошибочный вызов выполнится при повторе снова. Тот же ключ с другим телом: `…IDEMPOTENCYKEYREUSEDEXCEPTION` (422); неверный ключ: `…INVALIDIDEMPOTENCYKEYEXCEPTION` (400).
- Одновременные запросы с одним ключом могут выполниться оба: повторяй после ответа либо таймаута.
- Классический `/rest/` игнорирует заголовок.
