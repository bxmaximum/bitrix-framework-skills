# Page toolbar (`Bitrix\UI\Toolbar`)

The standard page header: title, action buttons, `main.ui.filter`, favorite star. PHP facade `Bitrix\UI\Toolbar\Facade\Toolbar` + component `bitrix:ui.toolbar`. There is no JS extension `ui.toolbar`: don't `Extension::load()` or `import` it; the client API is the global `BX.UI.ToolbarManager` from the component template.

```php
use Bitrix\Main\Loader;
use Bitrix\UI\Buttons\CreateButton;
use Bitrix\UI\Toolbar\ButtonLocation;
use Bitrix\UI\Toolbar\Facade\Toolbar;

Loader::includeModule('ui');

Toolbar::setTitle('Orders');                              // toolbar title only, not <title>
Toolbar::addFilter([                                      // bitrix:main.ui.filter params
    'FILTER_ID' => 'orders_grid', 'GRID_ID' => 'orders_grid',
    'FILTER' => $filterFields, 'ENABLE_LABEL' => true,
]);
Toolbar::addButton(CreateButton::create(['link' => '/local/orders/new/']), ButtonLocation::AFTER_TITLE);
Toolbar::addButton(['text' => 'Export', 'click' => 'BX.Vendor.Orders.export']); // array → Button
```

- Call the facade anywhere after the prolog (page, component class or template): the component renders at the end of the page build. State lives for one request.
- Bitrix24 site templates and `bitrix:ui.sidepanel.wrapper` already include `bitrix:ui.toolbar`. Include it yourself only in a custom site template, on an admin page (template `admin`) or for a second toolbar. Pages with their own header: `Toolbar::disable()`.
- Facade calls go to the toolbar `Toolbar::DEFAULT_ID` (`'default-toolbar'`) via `__callStatic`.

## Buttons

- `addButton(Button|array $button, $location = ButtonLocation::RIGHT)`; non-`Button` → `ArgumentTypeException`. Locations: `RIGHT`, `AFTER_TITLE` (primary "Create" action), `AFTER_FILTER` (only when a filter is set). `FILTER_RIGHT` is a deprecated alias of `AFTER_FILTER`.
- `deleteButtons(fn (Button $button, string $location): bool)` (true deletes), `shuffleButtons(fn (array $buttons): array, $location)` for `RIGHT` / `AFTER_FILTER`, `getButtons()`.
- Narrow screens collapse buttons to icons: a button without an icon needs `'dataset' => ['toolbar-collapsed-icon' => Icon::DOWNLOAD]`.
- With constant `AIR_SITE_TEMPLATE` defined, buttons get the air style automatically (**Since ui 26.200\***).

## Title, slots, extras

| Method | Note |
| --- | --- |
| `setTitle()`, `hideTitle()`, `setTitleMaxWidth()` | default title = `$APPLICATION->GetTitle()` |
| `addEditableTitle(?string $default)` | **Since ui 26.200\***; not saved: handle JS `finishEditing` |
| `addBeforeTitleHtml()`, `addAfterTitleHtml()`, `addUnderTitleHtml()`, `addRightCustomHtml($html, ['align' => 'right'])` | raw HTML, one value per slot (a new call replaces); escape user data |
| `setFilter(string $html)` | own filter markup instead of `addFilter()` |
| `addFavoriteStar()`, `deleteFavoriteStar()` | on by default |
| `setCopyLinkButton(['link' => ..., 'title' => ...])`, `setCopyLinkButton(null)` | **Since ui 26.200\*** |
| `addFullscreenButton()`, `removeFullscreenButton()` | **Since ui 26.500\***; default from option `ui:toolbar_fullscreen_button` |

The facade docblock lists `getCopyLinkButtonParams()`; the real method is `getCopyLinkButton()`. On older ui skip optional calls behind `method_exists(\Bitrix\UI\Toolbar\Toolbar::class, 'setCopyLinkButton')` (the facade only has `__callStatic`).

## Second toolbar

```php
use Bitrix\UI\Toolbar\Manager;

$toolbar = Manager::getInstance()->createToolbar('orders-secondary', ['filter' => $filterParams]);
$toolbar->addButton(['text' => 'Reload']);
$APPLICATION->IncludeComponent('bitrix:ui.toolbar', '', ['TOOLBAR_ID' => 'orders-secondary']);
```

## JS

```js
const toolbar = BX.UI.ToolbarManager.getDefaultToolbar(); // or .get(id); null when absent
toolbar?.getButton('orders-export')?.setWaiting(true);    // id from $button->setUniqId()
toolbar?.subscribe('finishEditing', (event) => saveTitle(event.getData().updatedTitle));
```

`getButtons()`, `getContainer()`; `setTitle()`, `getRightButtonsContainer()`, `getTitleEditor()` and events `beforeStartEditing` (`preventDefault()` cancels), `startEditing`, `finishEditing` (namespace `BX.UI.Toolbar`) are **Since ui 26.200\***; older ui: `getButton()` / `getButtons()` only.
