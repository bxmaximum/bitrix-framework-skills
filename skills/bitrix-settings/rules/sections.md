# Sections

| Section | Keys / notes | More |
| --- | --- | --- |
| `connections` | per name: `className` (`\Bitrix\Main\DB\MysqliConnection`, `PgsqlConnection`), `host`, `database`, `login`, `password`, `options` (2 = deferred connect), `charset`, `include_after_connected` | `bitrix-database` |
| `cache` | engine type and servers | `bitrix-caching` |
| `cache_flags` | `config_options` (TTL of options cache), ORM `<table>_max_ttl` / `_min_ttl` | `bitrix-caching` |
| `session` | `mode`, `handlers`, `lifetime` | `bitrix-sessions` |
| `cookies` | `secure` (false), `http_only` (true), `samesite` | |
| `crypto` | `crypto_key` — `CryptoField`, signer, SMTP passwords | |
| `exception_handling` | see below | |
| `routing` | `config` => route files in `/local/routes` | `bitrix-routing` |
| `messenger` | `run_mode`, `brokers`, `queues` | `bitrix-background-jobs` |
| `loggers` | PSR-3 loggers by id | `bitrix-logger` |
| `composer` | `config_path` to `composer.json`, relative to `DOCUMENT_ROOT` or absolute | |
| `http_client_options` | defaults merged into every `HttpClient` | `bitrix-http-client` |
| `pull` | push server | `bitrix-pull` |
| `smtp` | `rules/smtp.md` | |
| `ui` | `a11y`, `uploader.settings` (below) | `bitrix-ui` |
| `default_language` | map `lang => fallback lang` for languages outside the kernel list, not "site default language" | `bitrix-localization` |

## exception_handling

```php
'exception_handling' => [
    'value' => [
        'debug' => false, // never true in production: prints traces
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

- `handled_errors_types` — logged; `exception_errors_types` — turned into exceptions (adding `E_WARNING` makes every warning fatal).
- `track_modules` — optional module list for tracking.
- `log`: default file logger via `settings` (`file` relative to `DOCUMENT_ROOT` or absolute, `log_size` bytes), or own `class_name` (+ `required_file`, `extension`, `settings`).

## cookies, crypto, composer

```php
'cookies' => ['value' => ['secure' => true, 'http_only' => true, 'samesite' => 'Lax'], 'readonly' => false],
'crypto' => ['value' => ['crypto_key' => getenv('BX_CRYPTO_KEY')], 'readonly' => true], // secret: .settings_extra.php
'composer' => ['value' => ['config_path' => '../composer.json'], 'readonly' => true],
```

- `cookies` drive `Bitrix\Main\Web\Cookie` defaults; the PHP session cookie follows `php.ini` `session.cookie_*` (`bitrix-sessions`).
- Changing `crypto_key` makes existing `CryptoField` data and encrypted SMTP passwords unreadable.
- `composer.json` outside `DOCUMENT_ROOT`; the kernel includes its `vendor/autoload.php` (`bitrix-project-structure`).

## ui

```php
'ui' => [
    'value' => [
        'a11y' => ['restoreLostFocus' => true, 'useFocusTrapInDialogs' => true],
        'uploader' => [
            'settings' => [
                'defaultChunkSize' => '5M',
                'parallelChunkUpload' => true, // Since ui 26.600; ignored on Windows
                'maxParallelChunks' => 2,
            ],
        ],
    ],
    'readonly' => false,
],
```

`uploader.settings` also: `chunkMinSize`, `chunkMaxSize`; **Since ui 26.600** `presignedChunkUpload` (needs `clouds`), `presignedThreshold` (`'100M'`), `presignedUrlTtl` (s, 60…604800, default 1800), `presignedUrlBatchLimit` (100), `presignedRegisterInterval` (ms, 15000). Parallel chunks write sparse temp files — avoid on NFS/SMB temp dirs. Uploader usage: `bitrix-files`.
