# Подключения, обработчик подключения и блокировки

## Получить соединение

```php
use Bitrix\Main\Application;

$db = Application::getConnection();              // 'default'
$analytics = Application::getConnection('analytics');
$helper = $db->getSqlHelper();
```

## Секция `connections` в `.settings.php`

```php
'connections' => [
    'value' => [
        'default' => [
            'className' => \Bitrix\Main\DB\MysqliConnection::class,
            'host' => 'db',
            'database' => 'bx',
            'login' => 'bx',
            'password' => '***',
            'options' => \Bitrix\Main\DB\Connection::DEFERRED,
            'charset' => 'utf8mb4',                  // mysqli::set_charset()
        ],
        'analytics' => [
            'className' => \Bitrix\Main\DB\PgsqlConnection::class,
            'host' => 'pg',
            'database' => 'analytics',
            'login' => 'ro',
            'password' => '***',
            'options' => \Bitrix\Main\DB\Connection::DEFERRED,
            'include_after_connected' => '/var/www/local/php_interface/after_connect_analytics.php',
        ],
        'redis' => [
            'className' => \Bitrix\Main\Data\RedisConnection::class,
            'host' => 'redis',
            'port' => 6379,
        ],
    ],
    'readonly' => true,
],
```

- `options`: `Connection::PERSISTENT` (1), `Connection::DEFERRED` (2, подключение при первом запросе); объединяются через `|`.
- SQL-классы: `MysqliConnection`, `PgsqlConnection`. NoSQL: `Data\RedisConnection`, `Data\MemcachedConnection`, `Data\MemcacheConnection`.
- `/local/.settings.php` целиком заменяет `/bitrix/.settings.php`, а не отдельные секции (`bitrix-settings`).
- Реплики (модуль `cluster`): `bitrix-performance`.

## Обработчик подключения

- `php_interface/after_connect_d7.php` (`/local` приоритетнее `/bitrix`, подключается только один файл) выполняется **только для соединения `default`**.
- Для других соединений укажи абсолютный путь в `include_after_connected` (выше).
- Внутри файла `$this` — подключающееся соединение `Connection`:

```php
<?php
$this->queryExecute("SET NAMES 'utf8mb4'");
```

Создание `/local/php_interface/after_connect_d7.php` отключает файл из `/bitrix`: перенеси его инструкции.

## Именованные блокировки

```php
if (!$db->lock('vendor_import', 30))   // ждать до 30 секунд; 0 = не ждать
{
    return;                             // блокировка занята другим процессом
}
try
{
    // работа с исключительным доступом
}
finally
{
    $db->unlock('vendor_import');
}
```

- MySQL использует `GET_LOCK`, PostgreSQL — `bx_get_lock`; к имени добавляется уникальный ID сервера. Блокировка живёт в сессии БД.
- Для совместимости передавай неотрицательный таймаут или большое положительное значение для долгого ожидания. Прямой `GET_LOCK` MariaDB 11.4+ отвергает отрицательное значение. Проверенное ядро main 26.1000 преобразует его в `0xffffffff`; в старых ядрах 25.100.500/26.600 такого преобразования нет.
