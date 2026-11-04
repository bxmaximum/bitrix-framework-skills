# Настройка движка (`.settings.php`)

```php
'cache' => [
    'value' => [
        'type' => 'redis',                               // files | redis | memcached | memcache | apcu
        'redis' => ['host' => '127.0.0.1', 'port' => 6379],
        'serializer' => \Redis::SERIALIZER_IGBINARY,
        'sid' => 'site1',                                // префикс ключа: уникален для копии сайта на общем сервере
    ],
    'readonly' => false,
],
'cache_flags' => [                                       // отдельная секция верхнего уровня
    'value' => [
        'config_options' => 3600,                        // TTL кеша b_option
        'vendor_module_post_max_ttl' => 600,             // предел TTL ORM по таблице (0 отключает)
    ],
],
```

- Свой движок: `'type' => ['class_name' => MyEngine::class, 'extension' => 'redis']`. Несколько серверов: `'servers' => [['host' => …, 'port' => …]]`.
- Неизвестная строка `type` (опечатка, регистр) молча отключает кеш. Отсутствующий пользовательский класс или недоступный движок (нет расширения, сервер выключен) переключает на отсутствие кеша лишь с `E_USER_WARNING`.
