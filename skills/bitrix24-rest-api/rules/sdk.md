# Выбор SDK

SDK выполняет авторизацию, сборку запросов и разбор ошибок, но не добавляет методы и не снимает лимиты. Наличие обёртки и REST-контракт проверяй отдельно (`bitrix24-ai-tools`).

| SDK | Среда | Авторизация | Применение | REST 3.0 |
| --- | --- | --- | --- | --- |
| b24phpsdk (`composer require bitrix24/b24phpsdk`) | PHP-сервер | вебхук, OAuth | типизированный PHP, Маркет, генераторы списков | проверять конкретную версию/обёртку; номер SDK v3 сам по себе не означает REST3 |
| CRest (`github.com/bitrix-tools/crest`) | PHP-сервер | вебхук, OAuth | быстрая проверка, простое приложение | прямой HTTP для V3 |
| b24jssdk (`npm i @bitrix24/b24jssdk`) | iframe, Node.js | iframe, вебхук, OAuth | новый JS/TS внутри и вне iframe | `$b24.actions.v3` |
| BX24.js (`//api.bitrix24.com/api/v1/`) | только iframe приложения | iframe | совместимость старых приложений, системные диалоги | прямой HTTP для V3 |

- b24phpsdk: v1 для PHP 8.2–8.4, v3 для PHP 8.4+ с несовместимыми изменениями. Закрепляй major (`bitrix24/b24phpsdk:"^1.0"` либо `"^3.3"`): на PHP 8.4 Composer может выбрать v3. Вебхук: `ServiceBuilderFactory::createServiceBuilderFromWebhook()`, без обёртки — `core->call`. Многопортальное хранение токенов реализует приложение. `getResult()` возвращает массив: не вызывай на нём JS-методы `error()`/`data()`/`more()`/`next()`/`total()`.
- CRest хранит константы `C_REST_WEB_HOOK_URL`, `C_REST_CLIENT_ID`, `C_REST_CLIENT_SECRET` в `settings.php`, токены одного портала — в `settings.json`. Закрой файлы от веба и Git. Для многих порталов переопредели `getSettingData()`/`setSettingData()`.
- b24jssdk: `initializeB24Frame()` в iframe, вне Битрикс24 отклоняется — обрабатывай try/catch. `B24Hook.fromWebhookUrl(process.env.…)` и `B24OAuth` только на сервере. Не вставляй вебхук в браузерный код.
- BX24.js: `BX24.init`, `BX24.callMethod`, `BX24.callBatch`, `BX24.callBind`, `BX24.installFinish`; `result.next()` получает следующую страницу списка.
- Python b24pysdk и Go b24gosdk также официальные. Поддержка REST3 зависит от метода; у Python `prefer_version=3` выбирает V3 только для известных SDK методов, прочие остаются на старом API. Быстрые списки `call_list_fast()`/`as_list_fast()` и batch не поддерживают REST3 в проверенной документации.
- В PHP-проекте Битрикс без зависимости SDK используй клиент `HttpClient` из `rules/limits-errors.md`; перед кодом один раз прочитай `bitrix-framework`.
