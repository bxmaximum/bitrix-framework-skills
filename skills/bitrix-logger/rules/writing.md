# Реализации (`Bitrix\Main\Diag\`)

| Класс | Примечания |
| --- | --- |
| `FileLogger(string $fileName, ?int $maxSize = null)` | По умолчанию предел 1 МБ, затем файл переименовывается в `<file>.old`; `0` — без ротации |
| `SysLogger(string $prefix = '', int $flags = LOG_ODELAY, int $facility = LOG_USER)` | Системный syslog |
| `EventLogger(?string $module, ?string $auditType, ?callable $callback)` | `b_event_log` («Журнал событий» в админке); только малый объём |
| `LogFormatter($showArguments = false, $argMaxChars = 30)` | Стандартный формат; плейсхолдеры `{date}` `{host}` `{level}` `{delimiter}` `{exception}` `{trace}` и ключи контекста |
| `JsonLinesFormatter` (**Since main 25.300**; раньше `LogFormatter`) | Пишет только `$context` одной строкой JSON; текст сообщения теряется, добавляй его в контекст |

Настройки `level` и `formatter` применяются только к наследникам `Bitrix\Main\Diag\Logger`, не к Monolog.
## Запись

```php
$this->logger->error('Ошибка синхронизации заказа {orderId}: {exception}', ['orderId' => $id, 'exception' => $e]);
```

- Плейсхолдеры `{key}` берутся из контекста; `exception` выводит класс, сообщение и стек вызовов.
- Стек без исключения: `'trace' => \Bitrix\Main\Diag\Helper::getBackTrace(6, DEBUG_BACKTRACE_IGNORE_ARGS)`.
- Monolog (`composer require monolog/monolog`): возвращай `Monolog\Logger` из `constructor`; уровень и формат задавай средствами Monolog.
