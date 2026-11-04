# Выполнение вызовов

## Запрос и ответ

- Отправляй `POST {base}{method}` с JSON и заголовками `Content-Type: application/json`, `Accept: application/json`, `User-Agent: {integration}/{version}`.
  - База вебхука: `https://{host}/rest/{userId}/{code}/`.
  - База приложения: `client_endpoint`, например `https://{host}/rest/`; токен в поле `auth`.
- GET с query string оставь для ручной проверки: URL вместе с кодом вебхука попадает в историю и журналы доступа.
- Суффикс `.json` необязателен, JSON используется по умолчанию. `.xml` включает XML.
- Успех содержит `result` и `time` (`start`, `finish`, `duration`, `processing`; в облаке также `operating`, `operating_reset_at`). Списки добавляют `total`, `next`.
- Подписанные ссылки `DOWNLOAD_URL`/`urlMachine` скачивай отдельным GET с непустым `User-Agent`. Не меняй части авторизации ссылки.
- При смене домена облака старый адрес отвечает 301/302. Многие HTTP-клиенты меняют POST на GET и теряют тело, после чего метод сообщает об отсутствующих параметрах. Не следуй перенаправлениям POST автоматически. Обнови базу из `client_endpoint` токенов/события и храни по `member_id`.

## Проверка базового URL

Значение из env тоже проверяй до отправки реквизитов. HTTPS в примере не заменяет проверку реального значения:

```php
$baseUrl = (string)getenv('B24_WEBHOOK_URL');
if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' || !parse_url($baseUrl, PHP_URL_HOST))
{
    throw new \InvalidArgumentException('Нужен базовый HTTPS URL');
}
$baseUrl = rtrim($baseUrl, '/') . '/';
```

Не выводи URL в ошибке. У HTTP-клиента отключи автоматические редиректы POST; проверяй транспорт, HTTP-статус, JSON и REST-ошибку отдельно. Ядерный PHP-клиент нужен только внутри коробки (`rules/php-client.md`).

## Списки

- Типовая страница содержит 50 элементов. Передавай `next` ответа как `start` следующего запроса; останавливайся при отсутствии `next`. Размер уточняй для метода.
- Для экспорта избегай `start += 50`: каждая страница пересчитывает `total`. Если метод поддерживает, иди по ID с `start: -1`, отключая подсчёт:

```json
{"entityTypeId": 2, "order": {"id": "ASC"}, "filter": {">id": 1200}, "select": ["id", "title"], "start": -1}
```

  Последний `id` передавай в `>id`; остановись, когда страница короче 50. Ядро: `IRestService::getNavData()` (`start < 0` отключает подсчёт), `Bitrix\Rest\Engine\RestManager::DONT_CALCULATE_COUNT`.
- Регистр зависит от семейства: `id`/`>id` у `crm.item.*`, `ID`/`>ID` у legacy-методов.
- `crm.item.list` возвращает `result.items`, многие старые списки — массив прямо в `result`. Сверяй страницу метода.
- Явно указывай используемые поля в `select`.

## Пакетные вызовы batch

- `POST batch` с `{"halt": 0, "cmd": {"key": "method?query-string"}}`. Не более 50 команд (`CRestUtil::BATCH_MAX_LENGTH`). Лишние получают `ERROR_BATCH_LENGTH_EXCEEDED`, первые 50 выполняются.
- Команда — query string, собирай через `http_build_query()`. В JSON значения `cmd` дополнительно не кодируются; в URL GET весь batch кодируется дважды.

```php
$cmd = [];
foreach (array_slice($dealIds, 0, 50) as $i => $id)
{
    $cmd['deal_' . $i] = 'crm.item.get?' . http_build_query(['entityTypeId' => 2, 'id' => $id]);
}
$response = $client->call('batch', ['halt' => 0, 'cmd' => $cmd]); // клиент из rules/limits-errors.md
```

- Команды выполняются последовательно. Ссылка на прежний результат: `$result[key][FIELD]`; для списка добавь индекс строки: `$result[users][0][ID]`.
- `halt: 1` останавливает пакет на первой ошибке; `halt: 0` выполняет все команды.
- HTTP 200 возможен и при ошибках команд. Читай `result.result`, `result.result_error`, `result.result_total`, `result.result_next`, `result.result_time` по ключам команд. `result_error` присутствует всегда; проверяй число записей, не само наличие.
- Внутри batch запрещены вложенный `batch`, загрузка и скачивание файлов (`ERROR_BATCH_METHOD_NOT_ALLOWED`).
- Batch экономит HTTP-запросы, но не время методов: каждый вложенный вызов учитывается в `OPERATION_TIME_LIMIT`; в облаке весь пакет должен уложиться в 60 секунд.
- У batch REST 3.0 другой формат с `$ref`/`$refArray`: `rules/rest-v3.md`.
