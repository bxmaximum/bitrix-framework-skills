# Ошибки, ответы, рендеринг и области вызова

## Ошибки

- `$this->addError(new Error('Нужен заголовок', 'POST_TITLE_EMPTY', ['field' => 'title']))` или `$this->addErrors($result->getErrors())`, затем `return null`. Контракт: `bitrix-result-and-errors`.
- Исключения и PHP `Error` из действия, а также ошибки привязки параметров попадают в ответ с ошибками. Не выбрасывай исключения для ожидаемых отказов. **Since main 26.1000** при `debug=false` обычные исключения и PHP `Error` дают `Internal server error.` с исходным кодом. Исключения аргументов/привязки/валидации сохраняют текст; явные `addError(new Error(...))` также сохраняют текст. Подробнее: `bitrix-result-and-errors`. До 26.1000 не рассчитывай на автоматическое скрытие: возвращай безопасные ошибки на границе.
- `Bitrix\Main\Validation\ValidationException` из действия добавляет каждую свою ошибку (**Since main 26.250\***; раньше класса нет — вызывай `addErrors()` самостоятельно).

## JSON-обёртка

Любой результат, кроме `HttpResponse`, оборачивается в `AjaxJson`:

```json
{"status": "success", "data": {"id": 42}, "errors": []}
{"status": "error", "data": null, "errors": [{"message": "Нужен заголовок", "code": "POST_TITLE_EMPTY", "customData": {"field": "title"}}]}
```

Ключ `data` есть всегда: `return null` даёт `"data": null`. При ошибке `data` сохраняет возвращённое действием значение. `BX.ajax.runAction()` разрешает обещание только при `success`. Ключи передаются без изменений; для camelCase вызывай `$this->convertKeysToCamelCase($data)`.

## Классы ответов (`Bitrix\Main\Engine\Response\*`)

| Задача | Вернуть |
| --- | --- |
| Собственные статус, заголовки и тело | `Bitrix\Main\HttpResponse` |
| JSON без обёртки | `Json` |
| Обёртка вручную (замыкания маршрутов) | `AjaxJson::createSuccess($data)` / `createError(ErrorCollection)` |
| Перенаправление | `$this->redirectTo($url)` / `Redirect` |
| Файл из `b_file` | `BFile::createByFileId($id)`; уменьшенное изображение — `ResizedImage::createByImageId($id, $w, $h)` (размеры из разрешённого списка) |
| ZIP через nginx mod_zip | `Zip\Archive` |
| HTML компонента для AJAX | `$this->renderComponentAjax(...)` → `Component` (JSON с `html`, `assets`) |
| Ссылка для открытия приложения Битрикса | `OpenDesktopApp` / `OpenMobileApp` |

Подробнее: `bitrix-request-response`.

## HTML контроллера маршрута (**Since main 25.700**)

`renderView()`, `renderComponent()`, `renderExtension()` возвращают `Render\View|Component|Extension` (`HttpResponse`, страница внутри шаблона сайта; `withSiteTemplate: false` — без шаблона).

```php
return $this->renderView('blog/index', ['posts' => $posts]);
// → /local/modules/vendor.module/views/blog/index.php (затем /bitrix/modules/…)
return $this->renderComponent('bitrix:news.list', '', ['IBLOCK_ID' => 5]);
return $this->renderExtension('vendor.blog.app', ['id' => $id]);
```

- Путь `renderView()`: ID модуля берётся из каталога перед `/lib/` в пути файла контроллера; `/local` проверяется первым. Только `.php`; другое расширение вызывает `SystemException`. Начальный `/` задаёт путь от DOCUMENT_ROOT, без выхода за его пределы.
- Представление подключается через `$APPLICATION->IncludeFile()` внутри шаблона: не подключай header/footer, заголовок задавай `$APPLICATION->SetTitle()`.
- `renderExtension()` требует `controllerEntrypoint` (имя JS-функции) в `config.php` расширения; рендерит браузер, а не сервер.
- Перенос публичной страницы в контроллер: `bitrix-routing` → `rules/public-pages.md`. До 25.700 оставь страницу или используй `PublicPageController`.

## Подпись экспорта (**Since main 26.1000**)

Для нового компонента, экспортируемого через `Bitrix\Main\Controller\Export`, обычная подпись `Component\ParameterSigner` не даёт разрешения запуска. Выпускай `YourExportController::signExportParameters($componentName, $params)` на классе контроллера, который реально обработает запрос. Передавай результат в `signedParameters`, а то же имя — в `COMPONENT_NAME` с точным регистром. Подпись связана с именем компонента и identity целевого контроллера: перенос между разными контроллерами или компонентами не разрешён. Право пользователя на данные проверяй отдельно.

В ядре есть временный список совместимости собственных экспортов модулей; не рассчитывай на него для нового компонента. Старый формат параметров всё ещё декодируется у уже допущенного компонента — это не общий обход проверки имени. До 26.1000 используй прежний механизм подписи только вместе с фиксированным разрешённым набором компонентов и проверкой прав; метода `signExportParameters()` там нет.

## Области вызова

- `SCOPE_AJAX`: `/bitrix/services/main/ajax.php` и контроллеры маршрутов. `Csrf` выполняется только здесь.
- `SCOPE_REST` (классический): `restIntegration.enabled` и модуль `rest`. Авторизация через REST-токен, `Csrf` не запускается: проверяй права внутри действия.
- REST v3 (**Since rest 26.200\***): наследуй `Bitrix\Rest\V3\Controller\RestController`. При переопределении `getDefaultPreFilters()` / `getDefaultPostFilters()` объединяй с `parent::`: он содержит `IdempotencyFilter` (rest 26.600+). Подробнее: `bitrix24-rest-api` → `rules/rest-v3.md`, `bitrix-rest`.
- `SCOPE_CLI`: ограничивай действия через `ActionFilter\Scope(Scope::NOT_CLI)` и аналогичные фильтры.
