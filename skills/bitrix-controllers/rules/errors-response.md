# Errors, responses, rendering, scopes

## Errors

- `$this->addError(new Error('Title required', 'POST_TITLE_EMPTY', ['field' => 'title']))` or `$this->addErrors($result->getErrors())`, then `return null`. Contract design: `bitrix-result-and-errors`.
- Exceptions and PHP `Error`s thrown in the action, and binder failures, also end up as errors (message + code). Don't throw for expected failures.
- `Bitrix\Main\Validation\ValidationException` thrown in the action → each of its errors is added (**Since main 26.250\***; older kernels have no `ValidationException`: `addErrors()` yourself).

## JSON envelope

Any result that is not an `HttpResponse` is wrapped in `AjaxJson`:

```json
{"status": "success", "data": {"id": 42}, "errors": []}
{"status": "error", "data": null, "errors": [{"message": "Title required", "code": "POST_TITLE_EMPTY", "customData": {"field": "title"}}]}
```

`data` is always present: `null` return gives `"data": null`. On errors, `data` still holds whatever the action returned. `BX.ajax.runAction()` resolves only on `success`. Keys are sent as is; `$this->convertKeysToCamelCase($data)` for camelCase contracts.

## Response classes (`Bitrix\Main\Engine\Response\*`)

| Need | Return |
| --- | --- |
| Own status/headers/body | `Bitrix\Main\HttpResponse` |
| Plain JSON without envelope | `Json` |
| Envelope by hand (closure routes) | `AjaxJson::createSuccess($data)` / `createError(ErrorCollection)` |
| Redirect | `$this->redirectTo($url)` / `Redirect` |
| File from `b_file` | `BFile::createByFileId($id)`; resized: `ResizedImage::createByImageId($id, $w, $h)` (whitelist sizes) |
| ZIP via nginx mod_zip | `Zip\Archive` |
| Component HTML for AJAX | `$this->renderComponentAjax(...)` → `Component` (JSON with `html`, `assets`) |
| Deep link to the Bitrix app | `OpenDesktopApp` / `OpenMobileApp` |

Details: `bitrix-request-response`.

## HTML from routed controllers (**Since main 25.700**)

`renderView()`, `renderComponent()`, `renderExtension()` return `Render\View|Component|Extension` (`HttpResponse`, page inside the site template; `withSiteTemplate: false` for a bare page).

```php
return $this->renderView('blog/index', ['posts' => $posts]);
// → /local/modules/vendor.module/views/blog/index.php (then /bitrix/modules/…)
return $this->renderComponent('bitrix:news.list', '', ['IBLOCK_ID' => 5]);
return $this->renderExtension('vendor.blog.app', ['id' => $id]);
```

- `renderView()` path: the module id is the directory before `/lib/` in the controller's file path; `/local` is checked first. `.php` only (another extension → `SystemException`). A leading `/` means a path from DOCUMENT_ROOT; it must stay inside it.
- The view is `$APPLICATION->IncludeFile()`d into the template: no header/footer includes, set the title with `$APPLICATION->SetTitle()`.
- `renderExtension()` needs `controllerEntrypoint` (JS function name) in the extension's `config.php`; it renders in the browser, not SSR.
- Recipe for moving a public page to a controller: `bitrix-routing` → `rules/public-pages.md`. Before 25.700 keep the page or use `PublicPageController`.

## Scopes

- `SCOPE_AJAX`: `/bitrix/services/main/ajax.php` and controllers reached by routes. `Csrf` runs only here.
- `SCOPE_REST` (classic): `restIntegration.enabled` + `rest` module. Auth comes from the REST token, `Csrf` doesn't run: check rights in the action.
- REST v3 (**Since rest 26.200\***): extend `Bitrix\Rest\V3\Controller\RestController`. An overridden `getDefaultPreFilters()` / `getDefaultPostFilters()` must merge `parent::`, which carries `IdempotencyFilter` (rest 26.600+). Details: `bitrix24-rest-api` → `rules/rest-v3.md`, `bitrix-rest`.
- `SCOPE_CLI`: restrict actions with `ActionFilter\Scope(Scope::NOT_CLI)` etc.
