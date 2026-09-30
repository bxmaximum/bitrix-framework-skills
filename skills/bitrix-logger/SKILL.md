---
name: bitrix-logger
description: 'PSR-3 logging in Bitrix — Diag\FileLogger/SysLogger/EventLogger, LogFormatter, JsonLinesFormatter, loggers section, LoggerFactory + LoggerRegistry, named kernel loggers (main.HttpClient…), Monolog. Use when adding or configuring logs.'
---

# Logging (PSR-3)

Baseline: main 23.0+ · Verified: main 26.800.0

Code depends on `Psr\Log\LoggerInterface`; the implementation is chosen in `.settings.php` `loggers`. `AddMessage2Log()` — compat only.

## Implementations (`Bitrix\Main\Diag\`)

| Class | Notes |
| --- | --- |
| `FileLogger(string $fileName, ?int $maxSize = null)` | default max 1 MB, then renamed to `<file>.old`; `0` = no rotation |
| `SysLogger(string $prefix = '', int $flags = LOG_ODELAY, int $facility = LOG_USER)` | system syslog |
| `EventLogger(?string $module, ?string $auditType, ?callable $callback)` | `b_event_log` (*Event log* in admin) — low volume only |
| `LogFormatter($showArguments = false, $argMaxChars = 30)` | default; placeholders `{date}` `{host}` `{level}` `{delimiter}` `{exception}` `{trace}` + context keys |
| `JsonLinesFormatter` (**Since main 25.300**; older: `LogFormatter`) | writes only `$context` as one JSON line — the message text is dropped, put it into context |

`level` and `formatter` settings apply only to `Bitrix\Main\Diag\Logger` subclasses, not to Monolog.

## Get a logger

**Since main 26.250\*** — `LoggerFactory` + `LoggerRegistry`:

```php
use Bitrix\Main\Diag\LoggerFactory;
use Bitrix\Main\Diag\LoggerRegistry;

$logger = (new LoggerFactory())->createById('vendor.module.sync'); // NullLogger unless the id is enabled
(new LoggerRegistry())->enable('vendor.module.sync');               // option loggers:logger_is_enabled_<id> = Y
$logger = (new LoggerFactory())->createById('vendor.module.sync', [], false); // skip the registry check
```

- Enabled id without a `loggers` entry → default logger (`LOG_FILENAME` constant) or `NullLogger`.
- Kernel ids created this way stay silent until enabled: `main.Messenger.Receiver`, `main.Messenger.Requeue`, `main.config.feature`, `bizproc.imbot`, …
- `Logger::create($id, $params)` — deprecated since main 26.300, the only API before 26.250; returns `null` if `$id` is not configured: `$logger = Logger::create('id') ?? new NullLogger();`.

Inject into a service (module `.settings.php`):

```php
\Vendor\Module\Public\Service\SyncService::class => [
    'className' => \Vendor\Module\Public\Service\SyncService::class,
    'constructorParams' => static fn () => [(new \Bitrix\Main\Diag\LoggerFactory())->createById('vendor.module.sync')],
],
```

## `loggers` section

Put it into `/local/.settings_extra.php`: closures do not survive kernel rewrites of the main file (`bitrix-settings`).

```php
'services' => [
    'value' => [
        'vendor.formatter.json' => ['className' => \Bitrix\Main\Diag\JsonLinesFormatter::class],
    ],
],
'loggers' => [
    'value' => [
        'vendor.module.sync' => [
            'constructor' => static fn () => new \Bitrix\Main\Diag\FileLogger('/var/log/site/sync.log', 10 * 1024 * 1024),
            'level' => \Psr\Log\LogLevel::INFO,     // threshold
            'formatter' => 'vendor.formatter.json', // service id
        ],
        'main.HttpClient' => [
            'constructor' => static function (\Bitrix\Main\Web\Http\DebugInterface $debug, \Psr\Http\Message\RequestInterface $request) {
                $debug->setDebugLevel(\Bitrix\Main\Web\HttpDebug::ALL);
                return new \Bitrix\Main\Diag\FileLogger('/var/log/site/http.log');
            },
        ],
    ],
    'readonly' => true,
],
```

Entry forms: `constructor` closure (gets the factory `$params`) or `className` + `constructorParams` (array or closure returning one).

## Kernel logger ids

| Id | Used by | Closure params |
| --- | --- | --- |
| `main.Default` | `AddMessage2Log()`, default logger | `$logFileName`, `$showArgs` |
| `main.HttpClient` | `HttpClient` / PSR-18 handlers | `DebugInterface $debug`, `RequestInterface $request` |
| `main.GeoIpManager` | GeoIP manager | — |
| `main.EventLog.SysLogger`, `main.EventLog.FileLogger` | `CEventLog` backends | —, `$path, $maxSize` |

No `main.Mail` or `main.Engine` ids exist.

## Writing

```php
$this->logger->error('Sync of order {orderId} failed: {exception}', ['orderId' => $id, 'exception' => $e]);
```

- Placeholders `{key}` from context; `exception` renders class, message and trace.
- Stack trace without exception: `'trace' => \Bitrix\Main\Diag\Helper::getBackTrace(6, DEBUG_BACKTRACE_IGNORE_ARGS)`.
- Monolog (`composer require monolog/monolog`): return a `Monolog\Logger` from the `constructor` closure; set its level/formatter in Monolog.

## Checklist

- [ ] Services receive `LoggerInterface` (default `NullLogger`), no `new FileLogger()` inside business code.
- [ ] Logger ids prefixed with the module id; enabled via `LoggerRegistry` where `LoggerFactory` is used.
- [ ] Log files outside `DOCUMENT_ROOT`, rotation size set.
- [ ] No passwords, tokens or personal data in messages or context.
- [ ] `JsonLinesFormatter` callers put the message into context.
