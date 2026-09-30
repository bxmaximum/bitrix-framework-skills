# Popups and side panels

## `main.popup`

`import { Popup, Menu, PopupManager } from 'main.popup'` (globals `BX.Main.Popup`, `BX.Main.Menu`, `BX.Main.PopupManager`). A modal with title and buttons in the new design is `ui.system.dialog` (`rules/dialogs-menus.md`).

```js
import { Tag, Text } from 'main.core';
import { Popup, Menu } from 'main.popup';

const popup = new Popup({
    id: 'vendor-order-info',      // global id: a second Popup with it logs "Duplicate id"
    bindElement: button,          // node, {left, top} or MouseEvent; omit → centered
    content: Tag.render`<div>${Text.encode(order.title)}</div>`,
    autoHide: true, closeByEsc: true, closeIcon: true,
    cacheable: false,             // destroy on close instead of hiding
    events: { onClose: () => {} },
});
popup.show();

new Menu({
    bindElement: moreButton,
    items: [{ text: 'Archive', onclick: (event, item) => { archive(); item.getMenuWindow().close(); } }],
}).show();
```

- String `content` goes to `innerHTML`: pass an Element or escape. Menu item `text` is escaped, `html` is raw.
- `PopupManager.create({ id, ... })` returns the existing popup for that id; `getPopupById()`, `getCurrentPopup()`, `isAnyPopupShown()`, `getPopups()`.
- Since main 26.800 `PopupManager` handlers are `#private`: never call `handlePopupShow/Close/Destroy` or read `_popups`/`_currentPopup` (TypeError / deprecated).
- Compat only: `BX.PopupWindow`, `BX.PopupMenuWindow`, `BX.PopupWindowButton*` → `Popup`, `Menu`, `Button` from `ui.buttons`.
- Option `focusTrap: true` keeps Tab inside the popup (**Since main 26.600\***; older: `FocusTrap` from `ui.a11y`, see `rules/icons-typography-a11y.md`).

## `main.sidepanel` (slider)

`BX.SidePanel.Instance.open(url, options)` loads the URL in an iframe with `IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`. Inside that iframe `BX.SidePanel.Instance` is the top window's manager.

```js
BX.SidePanel.Instance.open('/local/orders/edit.php?id=42', {
    width: 900, cacheable: false, allowChangeHistory: false,
    events: { onClose: (event) => { /* event.denyAction() keeps it open */ } },
});

// inside the slider page, after save:
BX.SidePanel.Instance.postMessage(window, 'vendor:order-saved', { id: 42 });
BX.SidePanel.Instance.getSliderByWindow(window)?.close();

// opener page:
BX.Event.EventEmitter.subscribe('SidePanel.Slider:onMessage', (event) => {
    const [message] = event.getData();
    if (message.getEventId() === 'vendor:order-saved') {
        BX.Main.gridManager.reload('orders_grid');
    }
});
```

- Slider events: `onOpen`, `onLoad`, `onClose` (`denyAction()` cancels), `onCloseComplete`, `onDestroy`, `onMessage`.
- Open matching links automatically: `BX.SidePanel.Instance.bindAnchors({ rules: [{ condition: ['/local/orders/\\d+/'], options: { width: 900 } }] })`.
- The slider URL is a normal page: authorize and check rights there; users can open it directly.
- `cacheable: true` (default) keeps the iframe after close; use `false` for edit forms.

## Slider page server side

Wrap the page component in `bitrix:ui.sidepanel.wrapper` (paddings, toolbar, footer buttons):

```php
$APPLICATION->IncludeComponent('bitrix:ui.sidepanel.wrapper', '', [
    'POPUP_COMPONENT_NAME' => 'vendor:order.edit',
    'POPUP_COMPONENT_TEMPLATE_NAME' => '',
    'POPUP_COMPONENT_PARAMS' => ['ID' => $orderId],
    'BUTTONS' => ['save', 'cancel' => '/local/orders/'], // bitrix:ui.button.panel footer
]);
```

- The UI toolbar is shown by default: `HIDE_TOOLBAR => true`, or `PLAIN_VIEW => true` (no paddings, no toolbar). `USE_PADDING => false` removes paddings only.
- Page title and header buttons: `Toolbar` facade (`rules/toolbar.md`).
