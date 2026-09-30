# Call mechanics

## Request and response

- Send `POST {base}{method}` with a JSON body and headers `Content-Type: application/json`, `Accept: application/json`, `User-Agent: {integration}/{version}`.
  - Webhook base: `https://{host}/rest/{userId}/{code}/`.
  - App base: `client_endpoint` (for example `https://{host}/rest/`), token in the `auth` field.
- Use GET with a query string only for quick manual tests: the URL, webhook code included, lands in browser history and access logs.
- `.json` after the method name is optional (JSON is the default). `.xml` switches the response to XML.
- A successful response holds `result` and `time` (`start`, `finish`, `duration`, `processing`; cloud also `operating`, `operating_reset_at`). Lists add `total` and `next`.
- Download signed file links (`DOWNLOAD_URL`, `urlMachine`) with a separate GET and a non-empty `User-Agent`. Do not change the auth parts of the link.
- Domain change (cloud): the old host answers 301/302, and many HTTP clients replay a POST as a GET without the body. The method then reports missing parameters. Do not auto-follow redirects for POST. Refresh the base from `client_endpoint` (token response or event `auth`) and store it per `member_id`.

## Lists

- A page holds 50 items. Pass `next` from the response as `start` of the next call. Stop when `next` is absent.
- For exports, do not walk `start += 50`: every page recounts `total`. Page by ID and pass `start: -1` to skip the count:

```json
{"entityTypeId": 2, "order": {"id": "ASC"}, "filter": {">id": 1200}, "select": ["id", "title"], "start": -1}
```

  Put the last received `id` into `>id` and repeat until a page has fewer than 50 items. Kernel: `IRestService::getNavData()` (`start < 0` → no count) and `Bitrix\Rest\Engine\RestManager::DONT_CALCULATE_COUNT`.
- Field and filter key case follows the method family: `id` / `>id` in `crm.item.*`, `ID` / `>ID` in legacy methods.
- The result shape differs: `crm.item.list` returns `result.items`, many legacy lists return a plain array in `result`. Check the method page.
- Always pass `select` with the fields you use.

## Batch

- `POST batch` with `{"halt": 0, "cmd": {"key": "method?query-string"}}`. Up to 50 commands (`CRestUtil::BATCH_MAX_LENGTH`). Each extra one returns `ERROR_BATCH_LENGTH_EXCEEDED`, the first 50 still run.
- A command is a query string: build it with `http_build_query()`. In a JSON body the `cmd` values need no further encoding. In a GET URL the whole batch is encoded twice.

```php
$cmd = [];
foreach (array_slice($dealIds, 0, 50) as $i => $id)
{
    $cmd['deal_' . $i] = 'crm.item.get?' . http_build_query(['entityTypeId' => 2, 'id' => $id]);
}
$response = $client->call('batch', ['halt' => 0, 'cmd' => $cmd]); // $client: rules/limits-errors.md
```

- Commands run sequentially. Reference an earlier result as `$result[key][FIELD]`; for list results add the row index: `$result[users][0][ID]`.
- `halt: 1` stops at the first failed command, `halt: 0` runs all of them.
- The response is HTTP 200 even if commands fail. Read `result.result`, `result.result_error`, `result.result_total`, `result.result_next`, `result.result_time`, all keyed by command key. `result_error` is always present, so count its entries.
- Not allowed inside batch: `batch` itself and file upload or download (`ERROR_BATCH_METHOD_NOT_ALLOWED`).
- Batch saves HTTP requests (the rate limit), not method cost: every nested call counts towards `OPERATION_TIME_LIMIT`, and in the cloud the whole batch must finish within 60 s.
- REST 3.0 batch has a different format (`$ref`, `$refArray`): `rules/rest-v3.md`.
