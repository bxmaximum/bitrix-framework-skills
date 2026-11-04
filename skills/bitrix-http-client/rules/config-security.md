# Параметры, логирование и SSRF

## Параметры

Массив конструктора (или сеттеры) переопределяет секцию `http_client_options` из `.settings.php`, которая действует на каждый `new HttpClient()`, включая вызовы ядра:

```php
'http_client_options' => [
    'value' => [
        'socketTimeout' => 10,
        'streamTimeout' => 30,
        'useCurl' => true,
    ],
    'readonly' => false,
],
```

| Параметр | По умолчанию | Примечание |
| --- | --- | --- |
| `socketTimeout` / `streamTimeout` | 30 / 60 секунд | Подключение / чтение |
| `useCurl` | `false` | Нужен `php-curl`; лучше для https и асинхронности |
| `compress` | `false` | Принимать gzip |
| `redirect`, `redirectMax` | `true`, 5 | Только старый режим |
| `privateIp` | `true` | `false` блокирует частные, локальные и link-local адреса |
| `disableSslVerification` | Выключен | Только отладка |
| `bodyLengthMax` | 0 (без предела) | Ограничение размера ответа |
| `waitResponse` | `true` | `false`: прочитать заголовки и отключиться |
| `proxyHost`, `proxyPort`, `proxyUser`, `proxyPassword` | | |
| `headers`, `cookies` | | Начальные значения каждого запроса |
| `debugLevel` | `HttpDebug::DEFAULT` | Только с `$http->setLogger()`; для логгера из `.settings.php` задавай уровень в его конструкторе |

## Логирование

Клиент пишет через PSR-3 логгер `main.HttpClient` (`bitrix-logger`); без настройки записи нет. Пример ниже — только для локальной диагностики с безопасными данными: заголовки могут содержать `Authorization`/`Cookie`, URL — ключ вебхука, тело — секреты. Для рабочего окружения отключай запись трафика либо используй логгер с удалением секретов:

```php
'loggers' => [
    'value' => [
        'main.HttpClient' => [
            'constructor' => static function (\Bitrix\Main\Web\Http\DebugInterface $debug, \Psr\Http\Message\RequestInterface $request) {
                $debug->setDebugLevel(\Bitrix\Main\Web\HttpDebug::REQUEST_HEADERS | \Bitrix\Main\Web\HttpDebug::RESPONSE_HEADERS);
                return new \Bitrix\Main\Diag\FileLogger('/var/log/bitrix/http.log');
            },
            'level' => \Psr\Log\LogLevel::DEBUG,
        ],
    ],
],
```

Биты `HttpDebug`: `REQUEST_HEADERS`, `REQUEST_BODY`, `REQUEST`, `RESPONSE_HEADERS`, `RESPONSE_BODY`, `RESPONSE`, `CONNECT`, `ALL`; `0` отключает запись. `DIAGNOSTICS` (время) и `DEFAULT` доступны **Since main 26.250\***; раньше комбинируй `CONNECT | REQUEST_HEADERS | RESPONSE_HEADERS` только с безопасными данными. `BACKTRACE` доступен **Since main 26.700** (стек отдельной записью). Константы `HttpDebug::NONE` нет. Не записывай секреты из URL, заголовков и тел на production. <!-- verify:negative -->

## SSRF

- Пользовательский URL → `new HttpClient(['privateIp' => false])`. Имя разрешается один раз, проверенный IP используется для подключения; каждый переход проверяется повторно. Разрешены только `http`/`https`.
- Оставляй `privateIp => true` только для доверенных внутренних сервисов.
- `CFile::MakeFileArray($url)` скачивает http(s) с `privateIp => false`; `ftp(s)://` на частные IP блокируются только **Since main 26.800**. На старом ядре принимай от пользователей лишь `https?://`. Обработчики `OnMakeFileArray` обходят проверку.
- Подробнее: `bitrix-security`.

## `CHTTP` (только совместимость)

| Старый вызов | Использовать |
| --- | --- |
| `CHTTP::Download()`, `$http->Get()` | `HttpClient::download()`, `get()` |
| `CHTTP::PrepareData()` | `http_build_query()` |
| `CHTTP::ParseAuthRequest()` | `Context::getCurrent()->getServer()->parseAuthRequest()` |
| `CHTTP::URN2URI($urn)` | `(new Uri($urn))->toAbsolute($host)` |
