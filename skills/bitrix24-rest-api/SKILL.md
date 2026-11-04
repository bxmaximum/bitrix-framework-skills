---
name: bitrix24-rest-api
description: 'Вызовы REST Битрикс24 из приложения или внешнего клиента: облако и коробка, OAuth, вебхуки, batch, event.bind, QUERY_LIMIT_EXCEEDED, BX24.js. Публикация PHP-методов — bitrix-rest.'
---

# REST Битрикс24: клиентские вызовы

Baseline: Bitrix24 cloud; on-premise main 23.0+ · Verified: rest 26.1100.0 (main 26.1000.0), b24restdocs 2026-10-30

Здесь вызывают REST. Серверная публикация методов своего PHP-модуля — `bitrix-rest`. Проверка документации MCP и Vibecode — `bitrix24-ai-tools`. Облачному клиенту PHP-канон не нужен; для PHP-кода внутри коробки один раз прочитай `bitrix-framework`.

| Задача | Открыть |
| --- | --- |
| Вебхук, локальное приложение, OAuth Маркета, токены, scopes | `rules/auth.md` |
| Адрес/тело запроса, списки, быстрая пагинация, batch | `rules/calls.md` |
| Лимиты, коды ошибок, повторы и задержки | `rules/limits-errors.md` |
| PHP-клиент внутри коробки на ядерном HttpClient | `rules/php-client.md` |
| События, исходящий вебхук, офлайн-события, встройки | `rules/events-placements.md` |
| REST 3.0 (`/rest/api/`), Idempotency-Key, OpenAPI | `rules/rest-v3.md` |
| Выбор b24phpsdk, b24jssdk, BX24.js, CRest | `rules/sdk.md` |
| Особенности коробки | `rules/on-premise.md` |

## Общие правила

- Проверяй метод, поле и фильтр по MCP (`bitrix24-ai-tools`) либо официальной странице. На целевом портале `method.get` проверяет наличие и доступность метода, но не заменяет описание параметров.
- URL вебхука, `client_secret`, токены и `application_token` храни в env/хранилище секретов. Не включай их в Git, код браузера, логи и общие ссылки.
- Запрашивай только необходимые scopes. Дополнительно действуют права пользователя вызова.
- Проверяй ключ `error`, у классического batch также `result.result_error`; одного HTTP-статуса недостаточно.
- События и установки проверяй по `auth.application_token` для сохранённого `member_id`; встройки — по `APPLICATION_TOKEN`. Не доверяй неподтверждённым данным входящего запроса.
- Перед запросом проверяй HTTPS-схему базового URL из env (`rules/calls.md`). Быстро подтверждай события, сохраняй работу в очередь и обрабатывай идемпотентно.
