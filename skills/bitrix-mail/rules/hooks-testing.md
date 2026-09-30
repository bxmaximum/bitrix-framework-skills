# Hooks, queue processing, testing

## Hooks (module `main`)

| Event | Handler gets | Effect |
| --- | --- | --- |
| `OnBeforeEventAdd` | legacy: `(&$event, &$lid, &$arFields, &$messageId, &$files, &$languageId)` | `false` cancels before queue/immediate send. **Since main 26.250\*** also fires for `Mail\Event::send()`/`sendImmediate()`; before, only for `CEvent::*` |
| `OnBeforeEventSend` | legacy: `(&$arFields, &$eventMessage, Mail\Context $context, &$arResult)` | per template at send time (also in the queue worker); `false` skips this template |
| `OnBeforeMailSend` | D7: `$event->getParameter(0)` = mail params (`TO`, `SUBJECT`, `HEADER`, …) | alter or cancel any outgoing mail, including other modules' |
| `OnBeforePhpMail` | D7: `$event->getParameter('arguments')` object with `to`, `subject`, `message`, `additional_headers`, `additional_parameters`, `context` (by reference) | last hop before SMTP/`mail()` |
| `OnMailEventMailClickRedirect` | D7: `$event->getParameter(0)` = `['url' => …, 'isSigned' => bool]` | tracked-link click; return `EventResult` with `['url' => …]` to rewrite it — applied only if the origin stays the same. **Since main 26.800\*** |

Register legacy events with `addEventHandlerCompatible()`, D7 ones with `addEventHandler()` (`bitrix-events`).

```php
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Bitrix\Main\EventResult;

EventManager::getInstance()->addEventHandler('main', 'OnBeforeMailSend', static function (Event $event): ?EventResult {
    $params = $event->getParameter(0);
    if (str_ends_with((string)$params['TO'], '@blocked.example'))
    {
        // 'error' reaches Mail::sendResult() callers (Since main 26.800); earlier ERROR just cancels
        return new EventResult(EventResult::ERROR, ['error' => new Error('Domain blocked', 'VENDOR_BLOCKED')]);
    }

    return null;
});
```

- A `SUCCESS` result's parameters are `array_merge`d into the mail params, so return an array (`['TO' => $to]`) or return `null`. `SUCCESS` without an array, or a non-array return value, throws a `TypeError`.
- **Since main 26.800**, `OnBeforePhpMail` can reject with `$args->context->setSendingError(new Error(...))`.
- `OnBeforeEventSend` can route a template through a sender with `$context->setSenderIdentity(...)` (Since main 26.800).

## Queue processing

- `Mail\EventManager::checkEvents()` runs as a background job after every hit, unless option `main:check_events` is `N`. One run sends up to option `main:mail_event_bulk` (5) queued events, guarded by the DB lock `event`.
- Low traffic means mail is late. Busy sites, or sites that need a steady rate, should use cron. In `dbconn.php` add `define('BX_CRONTAB_SUPPORT', true);` so hits skip the queue (this also moves non-periodic agents to cron). Then run `php -f /path/to/bitrix/modules/main/tools/cron_events.php` every minute: it defines `BX_CRONTAB`, and the same job runs at the end of the script. Raise `mail_event_bulk` for volume, and don't set `check_events = N`, which disables the job on cron too. Agents and cron details: `bitrix-background-jobs`.
- Drain one batch now (tests, CLI): `CEvent::ExecuteEvents()`. It skips the cron/hit checks.

## Diagnostics

`b_event.SUCCESS_EXEC`:

| Value | Meaning |
| --- | --- |
| `N` | queued |
| `Y` | all templates sent |
| `P` | some templates failed |
| `F` | all templates failed (transport, or `StopException` in a template) |
| `0` | no template: check the type, `ACTIVE = Y`, site binding for `LID`, and `LANGUAGE_ID` |
| `E` | exception; logged by the exception handler, and the worker stops this run |

Rows are purged after option `main:mail_event_period` days (14) by `CEvent::CleanUpAgent()`.

## Testing

- Stage/QA whitelist: add `define('ONLY_EMAIL', 'qa@example.com');` to `dbconn.php`. Messages whose `TO` differs are dropped and still reported as sent.
- Capture instead of sending (no SMTP in use): define a global `custom_mail($to, $subject, $message, $additionalHeaders, $additionalParameters, $context)` before the first send (e.g. in `/local/php_interface/init.php`). Write the message to a file and return `true`.
- SMTP dialogue log: `smtp` → `debug` + `log_file`/`logFile` in `.settings.php` (`bitrix-settings`).
- `Mail::sendResult()` error codes tell a cancel from a transport failure. `Event::sendImmediate()` returns only a status letter.
