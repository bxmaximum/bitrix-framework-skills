# HttpRequest

В контроллере используй `$this->getRequest()`, вне его — `Context::getCurrent()->getRequest()`.

| Источник | API |
| --- | --- |
| Строка запроса | `getQuery('id')` / `getQueryList()` |
| Тело формы | `getPost('body')` / `getPostList()` |
| Загруженные файлы | `getFile('upload')` / `getFileList()` |
| Заголовок | `getHeader('X-Auth-Token')` / `getHeaders()` |
| Cookie без префикса, расшифрованный | `getCookie('guest_id')` / `getCookieList()`; исходный: `getCookieRaw()` |
| Объединённые строка запроса и форма | `get('id')`, `$request['id']` — только когда источник действительно не важен |

```php
$request = Context::getCurrent()->getRequest();

$id = (int)$request->getQuery('id');
$body = (string)$request->getPost('body');
$file = $request->getFile('upload'); // массив формата $_FILES либо null
```

- Значения проходят системные фильтры (проактивная защита). Это не защита от SQL/XSS: приводи типы, проверяй и экранируй.
- `getPostList()`/`getQueryList()` возвращают `ParameterDictionary`: `get()`, `getRaw()` (до фильтров), `getValues()`, `isEmpty()`, доступ как к массиву.
- Типизированный ввод контроллеров: параметры действия или DTO запроса (`bitrix-validation`).

## Тело JSON

Тело `application/json` разбирается при старте в отдельный список. Не входит в `get()`/`getPost()` и не связывается с параметрами действия, кроме `JsonController`: его фильтр `ContentType([JSON])` добавляет ключи верхнего уровня.

| Задача | API |
| --- | --- |
| Действие контроллера | Параметр `JsonPayload $payload` → `$payload->getData()`; `JsonController` требует тип содержимого и связывает ключи верхнего уровня по имени |
| Разобранное тело | `isJson()` + `getJsonList()` |
| Обязательный корректный JSON | `decodeJsonStrict()` (выбрасывает `SystemException`) |
| Исходное тело | `HttpRequest::getInput()` |

Не используй `json_decode(file_get_contents('php://input'))`.

## Сведения о запросе

```php
$request->getRequestMethod();   // 'GET', 'PUT'… (методов isGet()/isPut()/isDelete() нет)
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

Значения сервера: `Context::getCurrent()->getServer()` → `get('REMOTE_ADDR')`, `getDocumentRoot()`, `getHttpHost()`.
