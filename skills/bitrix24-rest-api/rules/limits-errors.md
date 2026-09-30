# Limits, errors, retries

## Cloud limits

| Limit | Value | Error |
| --- | --- | --- |
| Request rate, leaky bucket per portal | Enterprise: drains 5/s, blocks above 250; other plans: 2/s, 50 | `QUERY_LIMIT_EXCEEDED`, HTTP 503 |
| Method time, per app or webhook and method | sum of execution time over 10 one-minute buckets; limit set by the portal | `OPERATION_TIME_LIMIT`, HTTP 429 |
| One request | 60 s, then timeout | — |

- Apps on one server IP calling the same portal share its rate counter. Do not run parallel request streams from one IP.
- Batch counts as one HTTP request, but each nested call adds its own method time.
- On-premise: no request-rate limit in the `rest` module; the method-time limiter is off by default (`rules/on-premise.md`).

## Recognize an error

- Error body: `{"error": "CODE", "error_description": "…"}`. `error` may be an empty string: then only the description explains it (for example `400` + `Not found`). Test `array_key_exists('error', …)`, not its value.
- Batch: HTTP 200, failures in `result.result_error` by command key.
- REST 3.0: `{"error": {"code", "message", "validation"}}` (`rules/rest-v3.md`).
- Branch on the code, never on `error_description`: it is localized and changes.
- The same code can be system or method level; the status separates them (`ACCESS_DENIED` 401 = no REST on the plan, 403 = method rule).

## What to do per code

| Code | Action |
| --- | --- |
| `QUERY_LIMIT_EXCEEDED` | Retry with exponential backoff and jitter. Rejected before execution, so safe for writes |
| `OPERATION_TIME_LIMIT` | Pause this method for this app or webhook until `time.operating_reset_at` of the last successful response; others keep working |
| `INTERNAL_SERVER_ERROR`, `ERROR_UNEXPECTED_ANSWER` | Retry reads after a few seconds. Retry writes only if idempotent (REST 3.0 `Idempotency-Key`, or look the object up first) |
| `expired_token` | Refresh tokens (`rules/auth.md`), retry once |
| `NO_AUTH_FOUND`, `INVALID_CREDENTIALS`, `invalid_token` | Fix credentials; no retry |
| `insufficient_scope`, `ERROR_METHOD_NOT_FOUND` | Add the scope or fix the name; no retry |
| `INVALID_REQUEST` (`Https required`) | Use `https://` |
| `ACCESS_DENIED` (401), `FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN` (403) | Plan has no REST access; alert the portal admin, no retry |
| `OVERLOAD_LIMIT` | Manual block; stop and contact support |
| `user_access_error` | Admin must give the user access to the app |
| `PORTAL_DELETED` | Public part of the site is closed |
| Method errors (400/403) | Fix the request; a retry returns the same |

`FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN` is the Vibe+ plan refusal (**Since rest 26.650**, cloud only). Before that, and for other refusals, the code is `ACCESS_DENIED`.

Log `error`, `error_description`, HTTP status and method name. Never log tokens, webhook codes or `application_token`.

## Minimal client for `/local/`

Webhook URL from env; the full HttpClient API is in `bitrix-http-client`.

```php
<?php declare(strict_types=1);

namespace Vendor\Integration\Bitrix24;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;

final class RestClient
{
    private const RETRYABLE = ['QUERY_LIMIT_EXCEEDED'];

    public function __construct(private readonly string $baseUrl) // getenv('B24_WEBHOOK_URL'), ends with '/'
    {
    }

    public function call(string $method, array $params = [], int $attempt = 0): array
    {
        $http = new HttpClient(['socketTimeout' => 10, 'streamTimeout' => 60, 'redirect' => false]);
        $http->setHeader('Content-Type', 'application/json');
        $http->setHeader('Accept', 'application/json');

        $raw = $http->post($this->baseUrl . $method, Json::encode($params));
        $data = is_string($raw) && $raw !== '' ? Json::decode($raw) : null;

        if (!is_array($data))
        {
            throw new \RuntimeException('HTTP ' . $http->getStatus() . ' from ' . $method);
        }

        if (array_key_exists('error', $data))
        {
            $code = (string)$data['error'];
            if (in_array($code, self::RETRYABLE, true) && $attempt < 5)
            {
                usleep((2 ** $attempt) * 500_000 + random_int(0, 250_000));

                return $this->call($method, $params, $attempt + 1);
            }

            throw new \RuntimeException($method . ': ' . $code . ' ' . ($data['error_description'] ?? ''));
        }

        return $data;
    }
}
```

`Json::decode()` throws `Bitrix\Main\ArgumentException` on non-JSON bodies (proxy error pages): catch it where you handle transport errors. For long exports, move the loop to an agent or queue (`bitrix-background-jobs`).
