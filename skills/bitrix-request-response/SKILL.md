---
name: bitrix-request-response
description: 'Используй для HTTP-ввода и ответов Битрикса: Context, HttpRequest, JSON-тело, HttpResponse, cookies, перенаправления и файлы. Вместо $_GET/$_POST, header() и json_encode().'
---

# Приложение, контекст, запрос и ответ

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

| Задача | Читать |
| --- | --- |
| `Application`, `Context`, сайт, язык, доступ к сессии | `rules/application-context.md` |
| Строка запроса, форма, заголовки, cookies, файлы и тело JSON | `rules/request.md` |
| `HttpResponse`, `Json`/`AjaxJson`, перенаправления, файлы, cookies, `Converter` | `rules/response.md` |
| `Uri`, `UuidGenerator` | `rules/uri-uuid.md` |

## Обязательные правила

- Читай данные запроса через `$this->getRequest()` в контроллере или `Context::getCurrent()->getRequest()`. Не используй `$_GET`/`$_POST`/`$_COOKIE`/`$_SERVER`.
- Выбирай метод конкретного источника (`getQuery`/`getPost`/`getHeader`/`getCookie`). `get()` объединяет только строку запроса и форму.
- Тело JSON не входит в `get()`/`getPost()`; параметры действия привязываются к нему только в `JsonController`. Используй `getJsonList()` или `JsonPayload`.
- Отфильтрованные значения (`$request['x']`) не экранированы: приводи типы, проверяй и экранируй при выводе.
- Возвращай объекты ответа (`HttpResponse`, `Engine\Response\*`), не `header()`/`echo json_encode()`.
