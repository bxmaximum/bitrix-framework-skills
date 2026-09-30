# HttpRequest

`$this->getRequest()` in controllers, `Context::getCurrent()->getRequest()` elsewhere.

| Source | API |
| --- | --- |
| Query string | `getQuery('id')` / `getQueryList()` |
| Form body | `getPost('body')` / `getPostList()` |
| Uploaded files | `getFile('upload')` / `getFileList()` |
| Header | `getHeader('X-Auth-Token')` / `getHeaders()` |
| Cookie (prefix stripped, decrypted) | `getCookie('guest_id')` / `getCookieList()`; raw: `getCookieRaw()` |
| Query + form merged | `get('id')`, `$request['id']` — only if the source truly doesn't matter |

```php
$request = Context::getCurrent()->getRequest();

$id = (int)$request->getQuery('id');
$body = (string)$request->getPost('body');
$file = $request->getFile('upload'); // $_FILES-style array or null
```

- Values pass through system filters (proactive protection). That is not SQL/XSS protection: cast, validate, escape.
- `getPostList()`/`getQueryList()` return `ParameterDictionary`: `get()`, `getRaw()` (before filters), `getValues()`, `isEmpty()`, array access.
- Typed input for controllers: action params or a Request DTO (`bitrix-validation`).

## JSON body

`application/json` bodies are decoded at start into a separate list. They are not in `get()`/`getPost()` and are not bound to controller action params, except in `JsonController` (its `ContentType([JSON])` prefilter adds the top-level keys).

| Need | API |
| --- | --- |
| Controller action | `JsonPayload $payload` param → `$payload->getData()`; `JsonController` enforces the content type and binds top-level keys by name |
| Decoded body | `isJson()` + `getJsonList()` |
| Must be valid JSON | `decodeJsonStrict()` (throws `SystemException`) |
| Raw body | `HttpRequest::getInput()` |

No `json_decode(file_get_contents('php://input'))`.

## Request info

```php
$request->getRequestMethod();   // 'GET', 'PUT'… (no isGet()/isPut()/isDelete())
$request->isPost();
$request->isAjaxRequest();      // X-Requested-With: XMLHttpRequest
$request->isHttps();
$request->isAdminSection();     // /bitrix/admin/*
$request->getRequestUri();      // '/news/?id=1'
$request->getRequestedPage();   // '/news/index.php'
$request->getRequestedPageDirectory();
$request->getHttpHost();
$request->getRemoteAddress();
$request->getUserAgent();
$request->getAcceptedLanguages();
```

Server values: `Context::getCurrent()->getServer()` → `get('REMOTE_ADDR')`, `getDocumentRoot()`, `getHttpHost()`.
