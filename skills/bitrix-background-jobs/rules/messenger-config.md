# Messenger: queues, brokers, consumer

## Module queue (preferred)

`/local/modules/vendor.module/.settings.php` — read for **installed** modules only; the queue's module is loaded before its receiver is built.

```php
'messenger' => [
    'value' => [
        'queues' => [
            'vendor_welcome' => [
                'handler' => \Vendor\Module\Internals\Integration\Vendor\Module\MessageHandler\SendWelcomeMessageHandler::class,
                'limit' => 10,                   // messages per batch, default 50
                'total_processing_limit' => 50,  // default 100, keep >= limit
                'retry_strategy' => [
                    'max_retries' => 3,          // default 3
                    'delay' => 5,                // seconds, default 1
                    'multiplier' => 2,           // default 2
                    'max_delay' => 300,          // seconds, default 0 = no cap
                ],
                // 'broker' => 'vendor_heavy',   // optional, default broker otherwise
            ],
        ],
    ],
    'readonly' => true,
],
```

## Global config

`/local/.settings_extra.php` (or the main `.settings.php`, see `bitrix-settings`):

```php
'messenger' => [
    'value' => [
        'run_mode' => 'cli',  // 'web' (default): worker runs as a background job on hits
        'brokers' => [
            'default' => [
                'type' => 'db',
                'params' => ['table' => \Bitrix\Main\Messenger\Internals\Storage\Db\Model\MessengerMessageTable::class],
            ],
        ],
        'queues' => [
            'vendor_welcome' => [
                'handler' => \Vendor\Module\Internals\Integration\Vendor\Module\MessageHandler\SendWelcomeMessageHandler::class,
                'module' => 'vendor.module', // required here: default 'main' → your module is not loaded
            ],
        ],
    ],
    'readonly' => true,
],
```

- No `messenger` section at all → `web` mode with the built-in `default` DB broker. A section without `brokers.default` → `ConfigurationException`.
- Broker type: only `db`. No DSN strings, no Redis/AMQP transport.
- Heavy queue → own broker: tablet extending `MessengerMessageTable` (table created by your installer), declared under `messenger.brokers` of the global or your module `.settings.php`, referenced by the queue `broker` key. **Since main 26.700** each broker table has its own consumer lock; before, all DB queues shared one lock.
- A queue ID must be unique across all modules; a later module silently overrides an earlier one.

## Consumer (`run_mode` = `cli`)

```bash
cd /var/www/site && php bitrix/bitrix.php messenger:consume vendor_welcome other_queue --time-limit=300 --sleep=1
```

- Queues are separate arguments; none = all queues.
- Options: `-t/--time-limit` (seconds), `--sleep` (idle pause, default 1). No `--limit` (commented out in main 26.800) — batch size is the queue `limit`.
- Run under systemd/supervisor with restart; or cron with `--time-limit` shorter than the cron period.
- `web` mode is fine for low volume; it occupies PHP workers after responses.

## Kernel queues

List them in `messenger:consume` if you consume named queues. bizproc with `scheduler_use_messenger_transport=Y` needs `start_workflow_queue`, `scheduled_trigger_queue`, `resume_workflow_queue` and (**Since bizproc 26.1100**) `resume_workflow_delay_queue`, `resume_workflow_robot_delay_queue`, `resume_workflow_wait_workday_queue` — without them paused workflows never resume (`bitrix-bizproc`).

## Logging

Receiver notices and requeue errors go to loggers `main.Messenger.Receiver` / `main.Messenger.Requeue`, which are silent until enabled in `LoggerRegistry` (`bitrix-logger`).
