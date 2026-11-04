# Секция `loggers`

Размещай в `/local/.settings_extra.php`: замыкания теряются при перезаписи основного файла ядром (`bitrix-settings`). Диагностику HTTP включай только локально, с безопасными данными и защитой секретов: `bitrix-http-client` → `rules/config-security.md`.

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
            'level' => \Psr\Log\LogLevel::INFO,     // порог
            'formatter' => 'vendor.formatter.json', // ID сервиса
        ],
        'main.HttpClient' => [
            'constructor' => static function (\Bitrix\Main\Web\Http\DebugInterface $debug, \Psr\Http\Message\RequestInterface $request) {
                $debug->setDebugLevel(0); // HTTP-трафик по умолчанию не пишем; локальную диагностику включай отдельно
                return new \Bitrix\Main\Diag\FileLogger('/var/log/site/http.log');
            },
        ],
    ],
    'readonly' => true,
],
```

Формы записи: замыкание `constructor` (получает `$params` фабрики) либо `className` + `constructorParams` (массив или замыкание, возвращающее его).

## ID логгеров ядра

| ID | Использование | Параметры замыкания |
| --- | --- | --- |
| `main.Default` | `AddMessage2Log()`, стандартный логгер | `$logFileName`, `$showArgs` |
| `main.HttpClient` | `HttpClient` / обработчики PSR-18 | `DebugInterface $debug`, `RequestInterface $request` |
| `main.GeoIpManager` | Менеджер GeoIP | — |
| `main.EventLog.SysLogger`, `main.EventLog.FileLogger` | Обработчики `CEventLog` | —, `$path, $maxSize` |

ID `main.Mail` и `main.Engine` отсутствуют.
