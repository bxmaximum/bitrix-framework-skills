# События, очередь, тестирование

## События модуля `main`

| Событие | Аргументы обработчика | Действие |
| --- | --- | --- |
| `OnBeforeEventAdd` | Старый формат: `(&$event, &$lid, &$arFields, &$messageId, &$files, &$languageId)` | `false` отменяет постановку / немедленную отправку. **Since main 26.250\*** вызывается и для `Mail\Event::send()`/`sendImmediate()`, раньше только `CEvent::*` |
| `OnBeforeEventSend` | Старый формат: `(&$arFields, &$eventMessage, Mail\Context $context, &$arResult)` | Для каждого шаблона при отправке, включая очередь; `false` пропускает шаблон |
| `OnBeforeMailSend` | D7: `$event->getParameter(0)` — параметры письма (`TO`, `SUBJECT`, `HEADER`, …) | Изменить / отменить любое письмо, в том числе другого модуля |
| `OnBeforePhpMail` | D7: `$event->getParameter('arguments')` — объект с `to`, `subject`, `message`, `additional_headers`, `additional_parameters`, `context` по ссылке | Перед SMTP/`mail()` |
| `OnMailEventMailClickRedirect` | D7: `$event->getParameter(0)` = `['url' => …, 'isSigned' => bool]` | Переход по отслеживаемой ссылке; `EventResult` с `['url' => …]` меняет адрес только внутри исходного origin. **Since main 26.800\*** |

Старый формат регистрируй через `addEventHandlerCompatible()`, D7 — `addEventHandler()` (`bitrix-events`).

```php
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;

EventManager::getInstance()->addEventHandler('main', 'OnBeforeMailSend', static function (Event $event): ?EventResult {
    $params = $event->getParameter(0);
    if (str_ends_with((string)$params['TO'], '@blocked.example'))
    {
        // error приходит в Mail::sendResult() с main 26.800; раньше ERROR только отменял отправку
        return new EventResult(EventResult::ERROR, ['error' => new Error('Домен запрещён', 'VENDOR_BLOCKED')]);
    }

    return null;
});
```

- Параметры `SUCCESS` объединяются с параметрами письма через `array_merge`: возвращай массив (`['TO' => $to]`) либо `null`. `SUCCESS` без массива и возврат не-массива вызывают `TypeError`.
- **Since main 26.800** обработчик `OnBeforePhpMail` может отказать через `$args->context->setSendingError(new Error(...))`.
- `OnBeforeEventSend` может выбрать отправителя через `$context->setSenderIdentity(...)` (**Since main 26.800**).

## Обработка очереди

- `Mail\EventManager::checkEvents()` запускается фоновым заданием после хита, если `main:check_events` не `N`. За запуск отправляет до `main:mail_event_bulk` (5) событий под блокировкой БД `event`.
- При малом трафике почта опаздывает; для стабильной обработки используй cron. В `dbconn.php` добавь `define('BX_CRONTAB_SUPPORT', true);`: хиты пропустят очередь, непериодические агенты тоже перейдут в cron. Каждую минуту запускай `php -f /path/to/bitrix/modules/main/tools/cron_events.php`: скрипт определяет `BX_CRONTAB`, задание выполняется в конце. Для объёма увеличь `mail_event_bulk`. Не задавай `check_events = N`: это отключит обработку и в cron. Подробности: `bitrix-background-jobs`.
- Обработать пакет сразу (тест, CLI): `CEvent::ExecuteEvents()` обходит проверки cron/хита.

## Диагностика

`b_event.SUCCESS_EXEC`:

| Значение | Смысл |
| --- | --- |
| `N` | В очереди |
| `Y` | Все шаблоны отправлены |
| `P` | Часть шаблонов не отправлена |
| `F` | Все шаблоны завершились ошибкой транспорта либо `StopException` в шаблоне |
| `0` | Нет шаблона: проверь тип, `ACTIVE = Y`, привязку сайта `LID`, `LANGUAGE_ID` |
| `E` | Исключение, записанное обработчиком ошибок; текущий запуск обработчика прекращается |

Строки удаляет `CEvent::CleanUpAgent()` через `main:mail_event_period` дней (14).

## Тестирование

- Ограничение получателя stage/QA: `define('ONLY_EMAIL', 'qa@example.com');` в `dbconn.php`. Письма с другим `TO` отбрасываются, но отмечаются отправленными.
- Перехват без SMTP: до первой отправки определи глобальную `custom_mail($to, $subject, $message, $additionalHeaders, $additionalParameters, $context)`, например в `/local/php_interface/init.php`. Запиши письмо в файл, верни `true`.
- Журнал SMTP: `smtp` → `debug` + `log_file`/`logFile` в `.settings.php` (`bitrix-settings`).
- Коды `Mail::sendResult()` различают отмену и ошибку транспорта. `Event::sendImmediate()` возвращает только букву статуса.
