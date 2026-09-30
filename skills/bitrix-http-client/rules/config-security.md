# Options, logging, SSRF

## Options

Constructor array (or setters) override `.settings.php` → `http_client_options`, which applies to every `new HttpClient()` including kernel calls:

```php
'http_client_options' => [
    'value' => [
        'socketTimeout' => 10,
        'streamTimeout' => 30,
        'useCurl' => true,
    ],
    'readonly' => false,
],
```

| Option | Default | Note |
| --- | --- | --- |
| `socketTimeout` / `streamTimeout` | 30 / 60 s | connect / read |
| `useCurl` | `false` | needs `php-curl`; better for https and async |
| `compress` | `false` | accept gzip |
| `redirect`, `redirectMax` | `true`, 5 | legacy mode only |
| `privateIp` | `true` | `false` blocks private, loopback and link-local targets |
| `disableSslVerification` | off | debugging only |
| `bodyLengthMax` | 0 (no limit) | cap response size |
| `waitResponse` | `true` | `false`: read headers and disconnect |
| `proxyHost`, `proxyPort`, `proxyUser`, `proxyPassword` | | |
| `headers`, `cookies` | | defaults for every request |
| `debugLevel` | `HttpDebug::DEFAULT` | used only with `$http->setLogger()`; for the `.settings.php` logger set it in its constructor |

## Logging

The client logs through the PSR-3 logger `main.HttpClient` (`bitrix-logger`); nothing is written unless it is configured:

```php
'loggers' => [
    'value' => [
        'main.HttpClient' => [
            'constructor' => static function (\Bitrix\Main\Web\Http\DebugInterface $debug, \Psr\Http\Message\RequestInterface $request) {
                $debug->setDebugLevel(\Bitrix\Main\Web\HttpDebug::REQUEST_HEADERS | \Bitrix\Main\Web\HttpDebug::RESPONSE_HEADERS);
                return new \Bitrix\Main\Diag\FileLogger('/var/log/bitrix/http.log');
            },
            'level' => \Psr\Log\LogLevel::DEBUG,
        ],
    ],
],
```

`HttpDebug` bits: `REQUEST_HEADERS`, `REQUEST_BODY`, `REQUEST`, `RESPONSE_HEADERS`, `RESPONSE_BODY`, `RESPONSE`, `CONNECT`, `ALL`; `0` = off. `DIAGNOSTICS` (timings) and `DEFAULT` **Since main 26.250\***, older kernels: combine `CONNECT | REQUEST_HEADERS | RESPONSE_HEADERS`; `BACKTRACE` **Since main 26.700** (the backtrace becomes a separate log record). There is no `HttpDebug::NONE`. Bodies may contain secrets: don't log them in production.

## SSRF

- User-supplied URL → `new HttpClient(['privateIp' => false])`. The host is resolved once and the checked IP is used for the connection; every redirect hop is checked again. Only `http`/`https` are accepted.
- Keep `privateIp => true` only for trusted internal services.
- `CFile::MakeFileArray($url)` downloads http(s) with `privateIp => false`; `ftp(s)://` to private IPs is blocked only **since main 26.800**. On older kernels accept only `https?://` from users. `OnMakeFileArray` handlers bypass the check.
- More: `bitrix-security`.

## `CHTTP` (compat only)

| Legacy | Use |
| --- | --- |
| `CHTTP::Download()`, `$http->Get()` | `HttpClient::download()`, `get()` |
| `CHTTP::PrepareData()` | `http_build_query()` |
| `CHTTP::ParseAuthRequest()` | `Context::getCurrent()->getServer()->parseAuthRequest()` |
| `CHTTP::URN2URI($urn)` | `(new Uri($urn))->toAbsolute($host)` |
