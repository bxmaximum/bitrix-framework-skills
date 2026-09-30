# Connections, after-connect hook, locks

## Get a connection

```php
use Bitrix\Main\Application;

$db = Application::getConnection();              // 'default'
$analytics = Application::getConnection('analytics');
$helper = $db->getSqlHelper();
```

## `connections` in `.settings.php`

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

- `options`: `Connection::PERSISTENT` (1), `Connection::DEFERRED` (2, connect on first query); combine with `|`.
- SQL classes: `MysqliConnection`, `PgsqlConnection`. NoSQL: `Data\RedisConnection`, `Data\MemcachedConnection`, `Data\MemcacheConnection`.
- `/local/.settings.php` replaces `/bitrix/.settings.php` entirely, not per section (`bitrix-settings`).
- Replicas (`cluster` module): see `bitrix-performance`.

## After-connect hook

- `php_interface/after_connect_d7.php` (`/local` wins over `/bitrix`, only one file is included) runs **only for the `default` connection**.
- Other connections: absolute path in `include_after_connected` (as above).
- Inside the file `$this` is the connecting `Connection`:

```php
<?php
$this->queryExecute("SET NAMES 'utf8mb4'");
```

Creating `/local/php_interface/after_connect_d7.php` disables the `/bitrix` one: copy its statements.

## Named locks

```php
if (!$db->lock('vendor_import', 30))   // wait up to 30 s; 0 = don't wait
{
    return;                             // someone else holds it
}
try
{
    // exclusive work
}
finally
{
    $db->unlock('vendor_import');
}
```

- MySQL `GET_LOCK`, PostgreSQL `bx_get_lock`; the name is prefixed with the server unique ID. The lock lives in the DB session.
- Never `-1` ("wait forever"): MariaDB 11.4+ rejects negative timeouts. Use a large positive value.
