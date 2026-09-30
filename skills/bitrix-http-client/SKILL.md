---
name: bitrix-http-client
description: 'Bitrix\Main\Web\HttpClient — legacy get/post/download, PSR-18 sendRequest, async requests, http_client_options, HttpDebug logging, SSRF protection, GeoIp\Manager. Use for outgoing HTTP calls, webhooks, file downloads and IP geolocation.'
---

# HttpClient

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| GET/POST/JSON/download, PSR-18 requests, multipart, async | `rules/requests.md` |
| Options, global defaults, logging, SSRF, `CHTTP` replacements | `rules/config-security.md` |
| Country/city/time zone by IP | `rules/geoip.md` |

## Invariants

- Only `Bitrix\Main\Web\HttpClient`; `CHTTP` and raw `curl_*` / `file_get_contents($url)` are compat only.
- Set `socketTimeout` and `streamTimeout` explicitly: defaults are 30 s and 60 s.
- URLs from users, webhooks or imports: `'privateIp' => false` (the default `true` allows internal addresses).
- Check the status before decoding; legacy calls return `false` on transport errors (`getError()`), PSR-18 throws `ClientException`.
- Stream large bodies to a file (`download()`), don't hold them in memory.
