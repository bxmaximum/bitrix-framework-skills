---
name: bitrix-http-client
description: 'Используй для исходящих HTTP-запросов Битрикса через Web\HttpClient: вебхуки, загрузка файлов, PSR-18 sendRequest и асинхронные вызовы. Настройки, защита SSRF и GeoIp.'
---

# HttpClient

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

| Задача | Читать |
| --- | --- |
| GET/POST/JSON/загрузка, запросы PSR-18, multipart, асинхронность | `rules/requests.md` |
| Параметры, глобальные настройки, логи, SSRF, замены `CHTTP` | `rules/config-security.md` |
| Страна/город/часовой пояс по IP | `rules/geoip.md` |

## Обязательные правила

- Используй `Bitrix\Main\Web\HttpClient`; `CHTTP`, прямые `curl_*` / `file_get_contents($url)` допустимы только для совместимости.
- Явно задавай `socketTimeout` и `streamTimeout`; по умолчанию 30 и 60 секунд.
- Для URL от пользователя, вебхука или импорта ставь `'privateIp' => false` (стандартный `true` разрешает внутренние адреса).
- Проверяй статус до разбора ответа; старые вызовы при транспортной ошибке возвращают `false` (`getError()`), PSR-18 выбрасывает `ClientException`.
- Большие ответы сохраняй потоком в файл (`download()`), не держи в памяти.
