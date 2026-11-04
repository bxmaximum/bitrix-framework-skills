# Messenger: очереди, брокеры, потребитель

## Очередь модуля (основной вариант)

`/local/modules/vendor.module/.settings.php` читается только для **установленных** модулей; модуль очереди загружается до создания получателя.

```php
'messenger' => [
    'value' => [
        'queues' => [
            'vendor_welcome' => [
                'handler' => \Vendor\Module\Internals\Integration\Vendor\Module\MessageHandler\SendWelcomeMessageHandler::class,
                'limit' => 10,                   // сообщений в пакете, по умолчанию 50
                'total_processing_limit' => 50,  // по умолчанию 100, не меньше limit
                'retry_strategy' => [
                    'max_retries' => 3,          // по умолчанию 3: всего три попытки, включая исходную
                    'delay' => 5,                // секунды, по умолчанию 1
                    'multiplier' => 2,           // по умолчанию 2
                    'max_delay' => 300,          // секунды, по умолчанию 0 = без ограничения
                ],
                // 'broker' => 'vendor_heavy',   // необязательно; иначе брокер по умолчанию
            ],
        ],
    ],
    'readonly' => true,
],
```

## Глобальные настройки

`/local/.settings_extra.php` (или основной `.settings.php`, см. `bitrix-settings`):

```php
'messenger' => [
    'value' => [
        'run_mode' => 'cli',  // 'web' (по умолчанию): воркер выполняется после ответа на хите
        'brokers' => [
            'default' => [
                'type' => 'db',
                'params' => ['table' => \Bitrix\Main\Messenger\Internals\Storage\Db\Model\MessengerMessageTable::class],
            ],
        ],
        'queues' => [
            'vendor_welcome' => [
                'handler' => \Vendor\Module\Internals\Integration\Vendor\Module\MessageHandler\SendWelcomeMessageHandler::class,
                'module' => 'vendor.module', // здесь обязательно: по умолчанию 'main', свой модуль не загрузится
            ],
        ],
    ],
    'readonly' => true,
],
```

- Без секции `messenger` используется режим `web` и встроенный брокер БД `default`. Секция без `brokers.default` вызывает `ConfigurationException`.
- Поддерживается только тип брокера `db`. Нет строк DSN и транспорта Redis/AMQP.
- Тяжёлой очереди выделяй брокер: таблет, наследующий `MessengerMessageTable` (таблицу создаёт установщик), в `messenger.brokers` глобального или модульного `.settings.php`, с указанием ключа `broker` в очереди. **Since main 26.700** у таблицы брокера собственная блокировка потребителя; раньше все очереди БД делили одну.
- ID очереди должен быть уникальным среди модулей; последующий модуль молча переопределяет предыдущий.

В `retry_strategy` значение `max_retries=3` означает три попытки всего, не три повтора после первой. Первая задержка равна `delay * multiplier`; далее множитель применяется по номеру повтора, с разбросом jitter. `max_delay` лишь ограничивает задержку сверху. Исключение `RecoverableMessageException` восстанавливает счётчик: его повторы ограничивай сам.

## Потребитель (`run_mode` = `cli`)

```bash
cd /var/www/site && php bitrix/bitrix.php messenger:consume vendor_welcome other_queue --time-limit=300 --sleep=1
```

- Очереди передаются отдельными аргументами; без аргументов обрабатываются все.
- Параметры: `-t/--time-limit` (секунды), `--sleep` (пауза без работы, по умолчанию 1). Нет `--limit` (закомментирован в main 26.800); размер пакета задаёт `limit` очереди.
- Запускай под systemd/supervisor с перезапуском либо через cron с `--time-limit` меньше периода cron.
- Режим `web` подходит для малого объёма; он занимает PHP-воркеры после отправки ответа.

## Очереди ядра

Если потребляешь именованные очереди, перечисли нужные в `messenger:consume`. Для bizproc с `scheduler_use_messenger_transport=Y` нужны `start_workflow_queue`, `scheduled_trigger_queue`, `resume_workflow_queue` и (**Since bizproc 26.1100**) `resume_workflow_delay_queue`, `resume_workflow_robot_delay_queue`, `resume_workflow_wait_workday_queue`: без них приостановленные процессы не продолжатся (`bitrix-bizproc`).

## Логирование

Уведомления получателя и ошибки повторной постановки идут в `main.Messenger.Receiver` / `main.Messenger.Requeue`; они молчат до включения в `LoggerRegistry` (`bitrix-logger`).
