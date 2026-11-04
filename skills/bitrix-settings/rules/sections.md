# Секции

| Секция | Ключи и примечания | Подробнее |
| --- | --- | --- |
| `connections` | Для каждого имени: `className` (`\Bitrix\Main\DB\MysqliConnection`, `PgsqlConnection`), `host`, `database`, `login`, `password`, `options` (2 = отложенное соединение), `charset`, `include_after_connected` | `bitrix-database` |
| `cache` | Тип движка и серверы | `bitrix-caching` |
| `cache_flags` | `config_options` (TTL кеша настроек), ORM `<table>_max_ttl` / `_min_ttl` | `bitrix-caching` |
| `session` | `mode`, `handlers`, `lifetime` | `bitrix-sessions` |
| `cookies` | `secure` (false), `http_only` (true), `samesite` | |
| `crypto` | `crypto_key`: `CryptoField`, подписи, пароли SMTP | |
| `exception_handling` | См. ниже | |
| `routing` | `config` — файлы маршрутов в `/local/routes` | `bitrix-routing` |
| `messenger` | `run_mode`, `brokers`, `queues` | `bitrix-background-jobs` |
| `loggers` | Логгеры PSR-3 по ID | `bitrix-logger` |
| `composer` | `config_path` к `composer.json`, от `DOCUMENT_ROOT` или абсолютный | |
| `http_client_options` | Начальные параметры каждого `HttpClient` | `bitrix-http-client` |
| `pull` | Сервер push | `bitrix-pull` |
| `smtp` | `rules/smtp.md` | |
| `ui` | `a11y`, `uploader.settings` (ниже) | `bitrix-ui` |
| `default_language` | Карта `lang => запасной язык` для языков вне списка ядра, а не «основной язык сайта» | `bitrix-localization` |

## exception_handling

```php
'exception_handling' => [
    'value' => [
        'debug' => false, // на production не включать: выводит стек вызовов
        'handled_errors_types' => E_ALL & ~E_NOTICE & ~E_USER_NOTICE & ~E_DEPRECATED,
        'exception_errors_types' => E_ALL & ~E_NOTICE & ~E_WARNING & ~E_USER_NOTICE & ~E_USER_WARNING & ~E_COMPILE_WARNING & ~E_DEPRECATED,
        'ignore_silence' => false,
        'assertion_throws_exception' => true,
        'assertion_error_type' => E_USER_ERROR,
        'log' => [
            'settings' => ['file' => '/var/log/site/php-error.log', 'log_size' => 10000000],
        ],
    ],
    'readonly' => false,
],
```

- `handled_errors_types` — записываемые ошибки; `exception_errors_types` — превращаемые в исключения (`E_WARNING` делает каждое предупреждение фатальным).
- `track_modules` — необязательный список отслеживаемых модулей.
- `log`: стандартный файловый логгер через `settings` (`file` от `DOCUMENT_ROOT` либо абсолютный, `log_size` в байтах) или свой `class_name` (+ `required_file`, `extension`, `settings`).

## cookies, crypto, composer

```php
'cookies' => ['value' => ['secure' => true, 'http_only' => true, 'samesite' => 'Lax'], 'readonly' => false],
'crypto' => ['value' => ['crypto_key' => getenv('BX_CRYPTO_KEY')], 'readonly' => true], // секрет: .settings_extra.php
'composer' => ['value' => ['config_path' => '../composer.json'], 'readonly' => true],
```

- `cookies` задаёт начальные параметры `Bitrix\Main\Web\Cookie`; cookie сессии PHP следует `session.cookie_*` в `php.ini` (`bitrix-sessions`).
- Смена `crypto_key` делает существующие данные `CryptoField` и зашифрованные SMTP-пароли нечитаемыми.
- `composer.json` держи вне `DOCUMENT_ROOT`; ядро подключает его `vendor/autoload.php` (`bitrix-project-structure`).

## ui

```php
'ui' => [
    'value' => [
        'a11y' => ['restoreLostFocus' => true, 'useFocusTrapInDialogs' => true],
        'uploader' => [
            'settings' => [
                'defaultChunkSize' => '5M',
                'parallelChunkUpload' => true, // Since ui 26.600; на Windows игнорируется
                'maxParallelChunks' => 2,
            ],
        ],
    ],
    'readonly' => false,
],
```

Также в `uploader.settings`: `chunkMinSize`, `chunkMaxSize`; **Since ui 26.600** `presignedChunkUpload` (нужен `clouds`), `presignedThreshold` (`'100M'`), `presignedUrlTtl` (секунды, 60…604800, по умолчанию 1800), `presignedUrlBatchLimit` (100), `presignedRegisterInterval` (мс, 15000). Параллельные части пишут разреженные временные файлы: избегай NFS/SMB для временного каталога. Использование загрузчика: `bitrix-files`.
