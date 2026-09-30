---
name: bitrix-request-response
description: 'Application, Context, HttpRequest (query/post/JSON body), HttpResponse, Json/AjaxJson/Redirect/file responses, cookies, Converter, Uri, UuidGenerator. Use instead of $_GET/$_POST, header(), setcookie() or json_encode().'
---

# Application, Context, Request, Response

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| `Application`, `Context`, site, language, session access | `rules/application-context.md` |
| Reading query, form, headers, cookies, files, JSON body | `rules/request.md` |
| `HttpResponse`, `Json`/`AjaxJson`, redirect, files, cookies, `Converter` | `rules/response.md` |
| `Uri`, `UuidGenerator` | `rules/uri-uuid.md` |

## Invariants

- Request data only via `$this->getRequest()` (controllers) or `Context::getCurrent()->getRequest()`. Never `$_GET`/`$_POST`/`$_COOKIE`/`$_SERVER`.
- Use the getter for the known source (`getQuery`/`getPost`/`getHeader`/`getCookie`). `get()` merges query and form only.
- A JSON body is not in `get()`/`getPost()` and is bound to action params only in `JsonController`: `getJsonList()` or `JsonPayload`.
- Filtered values (`$request['x']`) are not escaped: cast, validate, escape on output.
- Output via response objects (`HttpResponse`, `Engine\Response\*`), not `header()`/`echo json_encode()`.
