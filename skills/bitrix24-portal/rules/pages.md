# Portal pages, slider, toolbar

## Where files live

| What | Where |
| --- | --- |
| Component (logic + template) | `/local/components/<vendor>/<name>/` |
| Services, controllers, events | `/local/modules/<vendor.module>/` |
| Public entry file (URL) | portal site root (`SITE_DIR`, usually `/`), e.g. `/my-reports/index.php` |
| Override a portal component template | `/local/templates/bitrix24/components/bitrix/<component>/<template>/` (see `bitrix-components`) |

- The public file is site content: header, one `IncludeComponent`, footer. No logic.
- Never edit `/bitrix/templates/bitrix24/` or product public files (`/.top.menu_ext.php`, `/company/…`); updates may replace them.
- URLs: flat pages with query params (`/my-reports/edit.php?id=5`) keep slider rules simple. Pretty URLs: `/local/routes` + `Bitrix\Main\Routing\Controllers\PublicPageController` pointing at the public file (`bitrix-routing`); JSON/AJAX: controllers (`bitrix-controllers`). Product sections use `urlrewrite.php` rules — `urlrewrite.php` is checked before routes, so don't reuse their paths and don't add new rules there.

## Page in the portal layout

```php
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
/** @global CMain $APPLICATION */
$APPLICATION->SetTitle('Reports');
$APPLICATION->IncludeComponent('vendor:report.list', '', ['PATH_TO_EDIT' => '/my-reports/edit.php?id=#ID#']);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
```

## Page that opens in a slider

```php
$APPLICATION->IncludeComponent('bitrix:ui.sidepanel.wrapper', '', [
    'POPUP_COMPONENT_NAME' => 'vendor:report.edit',
    'POPUP_COMPONENT_TEMPLATE_NAME' => '',
    'POPUP_COMPONENT_PARAMS' => ['ID' => $id],
    'PAGE_MODE' => false,                       // direct URL: open slider over the back URL
    'PAGE_MODE_OFF_BACK_URL' => '/my-reports/',
]);
```

- The slider requests the URL with `IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`. `bitrix24` `header.php`/`footer.php` return early on `IFRAME=Y` (no `<html>`, no assets), so a slider page without the wrapper renders broken.
- The wrapper calls `RestartBuffer()`, prints a full document and `exit`s in slider mode — use it from a public file, not inside a controller response.
- `PAGE_MODE` defaults to `true`: a direct URL renders the component as a normal page.
- Other params: `HIDE_TOOLBAR`/`PLAIN_VIEW` (toolbar is on by default; `USE_UI_TOOLBAR` is compat only), `USE_PADDING`, `USE_BACKGROUND_CONTENT`, `BUTTONS` (passed to `bitrix:ui.button.panel`: `save`, `apply`, `cancel`, `close`), `CLOSE_AFTER_SAVE` / `RELOAD_GRID_AFTER_SAVE` / `RELOAD_PAGE_AFTER_SAVE` (work after POST → redirect → GET).
- `POPUP_COMPONENT_PARAMS` always gets `IFRAME => true`.

## SidePanel JS

Open, `bindAnchors`, `postMessage`/`SidePanel.Slider:onMessage`, events and options: `bitrix-ui` → `rules/popups-sidepanel.md`. Portal specifics:

- Inside a slider iframe `BX.SidePanel.Instance` is the top window's manager — open nested sliders from it, not `window.open`.
- A `bindAnchors` rule (`condition: ['^/my-reports/edit\\.php']`) makes links to your page open in a slider; without it a plain link reloads the whole portal page.
- Refresh the opener via `postMessage` + `onMessage`, not `top.location.reload()`.

## Page toolbar

The template renders `bitrix:ui.toolbar` through a deferred buffer, so call the facade anywhere before the page ends (component `class.php` or template).

```php
\Bitrix\UI\Toolbar\Facade\Toolbar::addFilter([
    'FILTER_ID' => $gridId, 'GRID_ID' => $gridId, 'FILTER' => $fields, 'ENABLE_LABEL' => true,
]);
\Bitrix\UI\Toolbar\Facade\Toolbar::addButton(
    ['text' => 'Add', 'color' => \Bitrix\UI\Buttons\Color::SUCCESS, 'link' => '/my-reports/edit.php'],
    \Bitrix\UI\Toolbar\ButtonLocation::AFTER_TITLE,
);
\Bitrix\UI\Toolbar\Facade\Toolbar::addFavoriteStar();
```

- `addButton()` takes a `Bitrix\UI\Buttons\Button` or its params array (`text`, `color`, `link`, `click`, `icon`, …). Locations (`Bitrix\UI\Toolbar\ButtonLocation`): `RIGHT` (default), `AFTER_TITLE`, `AFTER_FILTER`.
- `addFilter()` includes `bitrix:main.ui.filter` with the given params — don't include the filter component again.
- The facade methods are resolved by `__callStatic` (`@method` docblock on `Bitrix\UI\Toolbar\Facade\Toolbar`); the real methods live in `Bitrix\UI\Toolbar\Toolbar`.

## Checklist

- [ ] Public file has no logic; code is in `/local/components` or `/local/modules`.
- [ ] Every slider page goes through `bitrix:ui.sidepanel.wrapper`.
- [ ] Toolbar buttons/filter added via `Toolbar::*`, not hand-made HTML.
- [ ] Slider → opener communication via `postMessage` / `SidePanel.Slider:onMessage`, not `top.location.reload()`.
