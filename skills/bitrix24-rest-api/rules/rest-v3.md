# REST 3.0

**Since rest 26.200\*** (transport and V3 controllers; absent in rest 25.300). Fallback: the classic method at `/rest/…`, which keeps working in parallel. Kernel: `rest/lib/V3/`, routes `api/#aplogin#/#ap#/#method#` and `api/#method#` in component `bitrix:rest.server`.

## When it applies

- Only methods already migrated. Docs list: tasks (`tasks.task.*`), Knowledge Base 2.0, mail, company structure (`humanresources.*`; `department.*` stays classic), event log, time tracking records, call follow-up.
- The same name can exist in both versions with different params and response (`tasks.task.get`). The method page header says which version it documents.
- The authoritative list for a given portal: `rest.documentation.openapi` (alias `documentation`) returns OpenAPI 3.0; methods are in `paths`, schemas in `components.schemas`. On-premise the set depends on module versions.
- `rest.scope.list` (alias `scopes`, V3 only) maps methods to scopes.

## Calling

- Webhook: `POST https://{host}/rest/api/{userId}/{code}/{method}`. App: `POST https://{host}/rest/api/{method}` with `auth` in the JSON body.
- JSON body only (`Content-Type: application/json`); form-data gives `…INVALIDJSONEXCEPTION`. Parameters are never read from the query string. Parameterless methods also accept GET.
- Without `/api/` the portal runs the classic method of the same name if it exists. A V3-only name without `/api/` returns `ERROR_METHOD_NOT_FOUND`.
- SDK: b24jssdk `$b24.actions.v3.*`; b24phpsdk, BX24.js and CRest have no V3 support, so use plain HTTP.

## Request shape

```json
{
  "select": ["id", "title", "responsible.name"],
  "filter": [["status", "=", "NEW"], {"logic": "or", "conditions": [["id", [1, 2]], ["id", "in", [3, 4]]]}],
  "order": {"id": "ASC"},
  "pagination": {"limit": 50, "page": 1}
}
```

- Related fields via dot in `select` (`responsible.name`). An unknown related field → `…UNKNOWNDTOPROPERTYEXCEPTION`. An unknown own field is silently dropped from the response: check what came back.
- Filter conditions at one level are AND. Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `between`. Only fields with `filterable: true` in `*.field.list`; sorting needs `sortable: true`.
- `pagination`: `limit` default 50, max 1000 (`PaginationStructure`), plus `offset` or `page`.

## Response and errors

- Success: `result.item` (one), `result.items` (list) or `result.result` (bool), plus `time`.
- Error: 4xx with `{"error": {"code", "message", "validation": [{"field", "message"}]}}`. Codes are `BITRIX_REST_V3_EXCEPTION_*`, derived from the exception class name: `…VALIDATION_REQUESTVALIDATIONEXCEPTION`, `…ACCESSDENIEDEXCEPTION` (403), `…INSUFFICIENTSCOPEEXCEPTION` (403), `…ENTITYNOTFOUNDEXCEPTION`, `…UNKNOWNFILTEROPERATOREXCEPTION`, `…METHODNOTFOUNDEXCEPTION`. Plan refusal keeps the plain code `FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN`.
- Message language: header `X-Bitrix-Rest-Response-Language` (read by the kernel, not documented).

## Batch 3.0

Body is a JSON array at the root; the classic `cmd` format fails with `…INVALIDSELECTEXCEPTION`.

```json
[
  {"method": "tasks.task.get", "query": {"id": 101, "select": ["id"]}, "as": "first"},
  {"method": "tasks.task.update", "query": {"id": {"$ref": "first.id"}, "fields": {"title": "Updated"}}},
  {"method": "tasks.task.list", "query": {"select": ["id"], "filter": [["id", ">", 100]]}, "as": "found"},
  {"method": "tasks.task.list", "query": {"select": ["title"], "filter": [["id", "in", {"$refArray": "found.id"}]]}}
]
```

- `$ref` takes one value (`name.field` or `index.field`, without the `item` wrapper), `$refArray` collects a field from all rows of a list result. Names in `as` must be unique.
- Sequential only, no nested batch, no transaction. One failed call returns a top-level `error` and no results, but earlier calls may already have written data.

## Idempotency-Key

**Since rest 26.600.** Fallback: before retrying a create, look the object up by your external key.

- Header `Idempotency-Key: <1–255 printable ASCII>` (UUID) on add, update and delete calls. A repeat with the same key and the same body within 24 h returns the stored response plus `Idempotent-Replayed: true`, and the action does not run again.
- The key is scoped to app or webhook + user + method. The body is compared as raw bytes (sha256): resend the exact same bytes, not re-serialized JSON.
- Only 200 responses are stored; a failed call runs again on retry. Same key, different body → `…IDEMPOTENCYKEYREUSEDEXCEPTION` (422); bad key → `…INVALIDIDEMPOTENCYKEYEXCEPTION` (400).
- Two concurrent requests with one key may both run: retry only after a response or a timeout.
- Ignored on classic `/rest/` calls.
