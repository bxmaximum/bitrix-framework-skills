# Buttons (`ui.buttons`)

One component, two sides: JS `import { Button, ButtonColor, ButtonSize, ButtonIcon, ButtonState, ButtonManager } from 'ui.buttons'` (globals `BX.UI.Button`, `BX.UI.ButtonColor`, …) and PHP `Bitrix\UI\Buttons\*`, which renders markup that the JS side picks up. Don't hand-write `ui-btn` markup when you need menus, counters or states.

## JS

```js
import { Button, ButtonColor, ButtonSize } from 'ui.buttons';

const button = new Button({
    text: 'Save',                 // set as textContent
    color: ButtonColor.SUCCESS,
    size: ButtonSize.SMALL,
    onclick: (btn, event) => {
        btn.setWaiting(true);     // spinner + disabled attribute
        save().finally(() => btn.setWaiting(false));
    },
});
button.renderTo(container);       // or container.append(button.render())
```

- Options: `id`, `link` (renders `<a>`), `icon: ButtonIcon.ADD`, `state`, `noCaps`, `round`, `dropdown`, `counter`, `dataset`, `props`, `className`, `disabled`, `events: { mouseenter: fn }`, `menu` (main.popup `Menu` options), `systemMenu` (**Since ui 26.600**, `ui.system.menu` options; older: `menu`).
- State: `setWaiting()`, `setClocking()`, `setDisabled()`, `setActive()`; `setText()`, `setColor()`, `setIcon()`, `getMenuWindow()`, `setSystemMenu(options | false)`.
- Presets: `SaveButton`, `CancelButton`, `CreateButton`, `AddButton`, `ApplyButton`, `CloseButton`, `SendButton`, `SettingsButton`; `SplitButton` + `*SplitButton` presets.
- Air design (**Since ui 26.200\***; older: `color`): `useAirDesign: true` + `style: AirButtonStyle.FILLED`; `leftCounter` / `rightCounter` take `ui.cnt` options. `ButtonCounterColor|Size|Style` are aliases of `CounterColor|Size|Style` (`rules/counters-labels.md`).

## PHP

```php
use Bitrix\UI\Buttons\Button;
use Bitrix\UI\Buttons\Color;
use Bitrix\UI\Buttons\Icon;
use Bitrix\UI\Buttons\JsEvent;

$button = Button::create([
    'text' => 'Export',                            // escaped on render
    'color' => Color::LIGHT_BORDER,
    'icon' => Icon::DOWNLOAD,
    'click' => new JsEvent('Vendor.Orders:export'), // EventEmitter event, data {button, event}
]);
echo $button->render();                            // loads its JS/CSS itself
```

```js
BX.Event.EventEmitter.subscribe('Vendor.Orders:export', (event) => {
    const { button } = event.getData();
    button.setWaiting(true);
});
```

- Click handlers: `new JsEvent('ns:name')` (preferred), `new JsHandler('BX.Vendor.Orders.export')` (global function, called with `(button, event)`; a plain string in `click` is the same), `new JsCode('...')` (eval'd: never put request or DB data in it).
- Keys: `text`, `color` (`Color::*`), `size` (`Size::SMALL|MEDIUM|LARGE|EXTRA_SMALL`), `icon` (`Icon::*`), `link` + `target`, `tag` (`Tag::LINK|BUTTON|INPUT|SUBMIT`), `id`, `counter`, `dataset`, `className` / `classList`, `noCaps`, `round`, `dropdown`, `state`, `menu`, `systemMenu`, `air` + `style` (`AirButtonStyle::*`).
- Presets: `SaveButton`, `CancelButton`, `CreateButton`, `AddButton`, `ApplyButton`, `CloseButton`, `SendButton`, `SettingsButton`, `Split\Button` (namespace `Bitrix\UI\Buttons`).

### Button menu

```php
use Bitrix\UI\Buttons\JsHandler;

Button::create([
    'text' => 'Export',
    'systemMenu' => ['items' => [
        ['id' => 'csv', 'title' => 'CSV', 'onClick' => new JsHandler('BX.Vendor.Orders.exportCsv')],
    ]],
]);
```

- `systemMenu` **Since ui 26.600**. Older: `'menu' => ['items' => [['text' => 'CSV', 'onclick' => new JsHandler(...)]]]`.
- `systemMenu` needs a non-empty `items` list, otherwise it is dropped with only a log line. It wins over `menu`; `setMenu()` with items removes `systemMenu`; `setSystemMenu(null)` removes it.

## JS access to PHP-rendered buttons

- `$button->setUniqId('orders-export')` (**Since ui 26.200\***; older: pass `$button->getUniqId()` to JS), then `ButtonManager.createByUniqId('orders-export')` or `ButtonManager.createFromNode(node)`.
- Toolbar buttons: `BX.UI.ToolbarManager.getDefaultToolbar().getButton(uniqId)` (`rules/toolbar.md`).
