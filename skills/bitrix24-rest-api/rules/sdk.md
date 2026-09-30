# SDK choice

SDKs handle auth, request building and error parsing. They do not add methods or lift limits, and none ships a method catalog: look names up (`bitrix24-ai-tools`).

| SDK | Runs | Auth | Pick when | REST 3.0 |
| --- | --- | --- | --- | --- |
| b24phpsdk (`composer require bitrix24/b24phpsdk`) | PHP server | webhook, OAuth | typed PHP client, Marketplace apps, large lists via generators | no |
| CRest (`github.com/bitrix-tools/crest`) | PHP server | webhook, OAuth | quick check, simple local app | no |
| b24jssdk (`npm i @bitrix24/b24jssdk`) | browser frame, Node.js | frame, webhook, OAuth | new JS/TS code in or outside the frame | yes, `$b24.actions.v3` |
| BX24.js (`//api.bitrix24.com/api/v1/`) | app frame only | frame | legacy frame apps, system dialogs | no |

- b24phpsdk: major v1 for PHP 8.2–8.4, v3 for PHP 8.4+ with breaking changes. Pin it (`bitrix24/b24phpsdk:"^1.0"` or `"^3.3"`), or Composer may pick v3 on PHP 8.4. Webhook entry: `ServiceBuilderFactory::createServiceBuilderFromWebhook()`; methods without a wrapper go through `core->call`. Multi-portal token storage is yours to build.
- CRest keeps config in `settings.php` constants (`C_REST_WEB_HOOK_URL`, `C_REST_CLIENT_ID`, `C_REST_CLIENT_SECRET`) and tokens of a single portal in `settings.json`. Keep both outside the web root and VCS. For many portals override `getSettingData()` / `setSettingData()`.
- b24jssdk: `initializeB24Frame()` in the frame (rejects outside Bitrix24, so wrap in try/catch); `B24Hook.fromWebhookUrl(process.env.…)` and `B24OAuth` on the server only. Never put a webhook into browser code.
- BX24.js: `BX24.init`, `BX24.callMethod`, `BX24.callBatch`, `BX24.callBind`, `BX24.installFinish`. For lists, `result.next()` fetches the next page.
- Python (b24pysdk) and Go (b24gosdk) SDKs are official too. They support REST 3.0, but their list walking and batch work only with the classic API.
- Inside a Bitrix PHP project with no SDK dependency, use the `HttpClient` client from `rules/limits-errors.md`.
