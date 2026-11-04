# Всплывающие окна и боковая панель

## `main.popup`

`import { Popup, Menu, PopupManager } from 'main.popup'`: глобальные BX.Main.Popup, BX.Main.Menu, BX.Main.PopupManager. Модальное окно нового дизайна с заголовком / кнопками — ui.system.dialog (`rules/dialogs-menus.md`).

```js
import { Tag, Text } from 'main.core';
import { Popup, Menu } from 'main.popup';

const popup = new Popup({
    id: 'vendor-order-info',      // глобальный ID: второй Popup запишет Duplicate id
    bindElement: button,          // узел, {left, top} либо MouseEvent; без него по центру
    content: Tag.render`<div>${Text.encode(order.title)}</div>`,
    autoHide: true, closeByEsc: true, closeIcon: true,
    cacheable: false,             // уничтожить при закрытии, а не скрыть
    events: { onClose: () => {} },
});
popup.show();

new Menu({
    bindElement: moreButton,
    items: [{ text: 'Архивировать', onclick: (event, item) => { archive(); item.getMenuWindow().close(); } }],
}).show();
```

- Строковый content попадает в innerHTML: Element либо экранирование. text пункта Menu экранируется, html — сырой.
- `PopupManager.create({ id, ... })` возвращает существующее окно этого ID. Также `getPopupById()`, `getCurrentPopup()`, `isAnyPopupShown()`, `getPopups()`.
- **Since main 26.800** обработчики PopupManager приватные (#private): не вызывай handlePopupShow/Close/Destroy, не читай _popups/_currentPopup (TypeError / устаревший API).
- Для совместимости: BX.PopupWindow, BX.PopupMenuWindow, BX.PopupWindowButton*; новый код — Popup, Menu, Button из ui.buttons.
- focusTrap: true удерживает Tab внутри окна (**Since main 26.600\***; раньше FocusTrap из ui.a11y, `rules/icons-typography-a11y.md`).

## main.sidepanel (слайдер)

`BX.SidePanel.Instance.open(url, options)` загружает iframe с IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER. В нём BX.SidePanel.Instance — менеджер верхнего окна.

```js
BX.SidePanel.Instance.open('/local/orders/edit.php?id=42', {
    width: 900, cacheable: false, allowChangeHistory: false,
    events: { onClose: (event) => { /* event.denyAction() keeps it open */ } },
});

// внутри страницы слайдера после сохранения:
BX.SidePanel.Instance.postMessage(window, 'vendor:order-saved', { id: 42 });
BX.SidePanel.Instance.getSliderByWindow(window)?.close();

// страница, открывшая слайдер:
BX.Event.EventEmitter.subscribe('SidePanel.Slider:onMessage', (event) => {
    const [message] = event.getData();
    if (message.getEventId() === 'vendor:order-saved') {
        BX.Main.gridManager.reload('orders_grid');
    }
});
```

- События: onOpen, onLoad, onClose (denyAction() отменяет), onCloseComplete, onDestroy, onMessage.
- Автоматическое открытие ссылок: `BX.SidePanel.Instance.bindAnchors({ rules: [{ condition: ['/local/orders/\\d+/'], options: { width: 900 } }] })`.
- URL слайдера — обычная страница, доступная напрямую: проверяй авторизацию и права на ней.
- cacheable: true по умолчанию сохраняет iframe после закрытия; у формы правки ставь false.

## Серверная страница слайдера

Оберни компонент страницы в bitrix:ui.sidepanel.wrapper: отступы, панель инструментов, нижние кнопки:

```php
$APPLICATION->IncludeComponent('bitrix:ui.sidepanel.wrapper', '', [
    'POPUP_COMPONENT_NAME' => 'vendor:order.edit',
    'POPUP_COMPONENT_TEMPLATE_NAME' => '',
    'POPUP_COMPONENT_PARAMS' => ['ID' => $orderId],
    'BUTTONS' => ['save', 'cancel' => '/local/orders/'], // нижняя панель bitrix:ui.button.panel
]);
```

- Панель по умолчанию видна. HIDE_TOOLBAR => true скрывает её; PLAIN_VIEW => true убирает и отступы, и панель; USE_PADDING => false — только отступы.
- Заголовок и кнопки шапки: фасад Toolbar (`rules/toolbar.md`).

**Since ui 26.700** LEFT_PANEL_RESIZABLE у ui.sidepanel.wrapper принимает пять обязательных int: INITIAL_WIDTH, MIN_WIDTH, MAX_WIDTH, MIN_CONTENT_WIDTH, KEYBOARD_STEP. Начальная ширина должна лежать в допустимом диапазоне, остальные минимумы / шаг — положительные. Строка '280' не подходит; некорректный набор молча выключает опцию. STORAGE необязателен: TYPE = localStorage, непустой KEY и VERSION = 1. На старом компоненте используй фиксированную ширину левой панели.
