# Получение логгера

**Since main 26.250\*** — `LoggerFactory` + `LoggerRegistry`:

```php
use Bitrix\Main\Diag\LoggerFactory;
use Bitrix\Main\Diag\LoggerRegistry;

$logger = (new LoggerFactory())->createById('vendor.module.sync'); // NullLogger, пока ID не включён
(new LoggerRegistry())->enable('vendor.module.sync');               // настройка loggers:logger_is_enabled_<id> = Y
$logger = (new LoggerFactory())->createById('vendor.module.sync', [], false); // без проверки реестра
```

- Включённый ID без записи `loggers` использует стандартный логгер (константа `LOG_FILENAME`) либо `NullLogger`.
- Логгеры ядра с такой фабрикой молчат до включения: `main.Messenger.Receiver`, `main.Messenger.Requeue`, `main.config.feature`, `bizproc.imbot`, …
- `Logger::create($id, $params)` устарел с main 26.300; до 26.250 это единственный API. Возвращает `null`, если ID не настроен: `$logger = Logger::create('id') ?? new NullLogger();`.

Внедрение в сервис (`.settings.php` модуля):

```php
\Vendor\Module\Public\Service\SyncService::class => [
    'className' => \Vendor\Module\Public\Service\SyncService::class,
    'constructorParams' => static fn () => [(new \Bitrix\Main\Diag\LoggerFactory())->createById('vendor.module.sync')],
],
```
