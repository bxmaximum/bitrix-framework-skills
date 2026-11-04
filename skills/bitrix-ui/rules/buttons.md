# Кнопки (`ui.buttons`)

JS: `import { Button, ButtonColor, ButtonSize, ButtonIcon, ButtonState, ButtonManager } from 'ui.buttons'` (глобальные `BX.UI.Button`, `BX.UI.ButtonColor`, …). PHP `Bitrix\UI\Buttons\*` выводит разметку, подхватываемую JS. Для меню, счётчиков и состояний не пиши `ui-btn` вручную.

## JS

```js
import { Button, ButtonColor, ButtonSize } from 'ui.buttons';

const button = new Button({
    text: 'Сохранить',             // устанавливается через textContent
    color: ButtonColor.SUCCESS,
    size: ButtonSize.SMALL,
    onclick: (btn, event) => {
        btn.setWaiting(true);     // индикатор ожидания и disabled
        save().finally(() => btn.setWaiting(false));
    },
});
button.renderTo(container);       // либо container.append(button.render())
```

- Параметры: `id`, `link` (тег `<a>`), `icon: ButtonIcon.ADD`, `state`, `noCaps`, `round`, `dropdown`, `counter`, `dataset`, `props`, `className`, `disabled`, `events: { mouseenter: fn }`, `menu` (настройки `Menu` main.popup), `systemMenu` (**Since ui 26.600**, настройки `ui.system.menu`; раньше — menu).
- Состояние: `setWaiting()`, `setClocking()`, `setDisabled()`, `setActive()`; `setText()`, `setColor()`, `setIcon()`, `getMenuWindow()`, `setSystemMenu(options | false)`.
- Готовые кнопки: `SaveButton`, `CancelButton`, `CreateButton`, `AddButton`, `ApplyButton`, `CloseButton`, `SendButton`, `SettingsButton`; `SplitButton` и варианты `*SplitButton`.
- Дизайн Air (**Since ui 26.200\***; раньше — color): `useAirDesign: true` + `style: AirButtonStyle.FILLED`; `leftCounter` / `rightCounter` принимают параметры `ui.cnt`. `ButtonCounterColor|Size|Style` — псевдонимы `CounterColor|Size|Style` (`rules/counters-labels.md`).

## PHP

```php
use Bitrix\UI\Buttons\Button;
use Bitrix\UI\Buttons\Color;
use Bitrix\UI\Buttons\Icon;
use Bitrix\UI\Buttons\JsEvent;

$button = Button::create([
    'text' => 'Экспорт',                            // экранируется при выводе
    'color' => Color::LIGHT_BORDER,
    'icon' => Icon::DOWNLOAD,
    'click' => new JsEvent('Vendor.Orders:export'), // событие EventEmitter, данные {button, event}
]);
echo $button->render();                            // сам загружает JS/CSS
```

```js
BX.Event.EventEmitter.subscribe('Vendor.Orders:export', (event) => {
    const { button } = event.getData();
    button.setWaiting(true);
});
```

- Нажатие: `new JsEvent('ns:name')` предпочтителен; `new JsHandler('BX.Vendor.Orders.export')` — глобальная функция с `(button, event)`, простая строка click работает так же. `new JsCode('...')` выполняется через eval: не вставляй данные запроса / БД.
- Ключи: `text`, `color` (`Color::*`), `size` (`Size::SMALL|MEDIUM|LARGE|EXTRA_SMALL`), `icon` (`Icon::*`), `link` + `target`, `tag` (`Tag::LINK|BUTTON|INPUT|SUBMIT`), `id`, `counter`, `dataset`, `className` / `classList`, `noCaps`, `round`, `dropdown`, `state`, `menu`, `systemMenu`, `air` + `style` (`AirButtonStyle::*`).
- Готовые классы `Bitrix\UI\Buttons`: `SaveButton`, `CancelButton`, `CreateButton`, `AddButton`, `ApplyButton`, `CloseButton`, `SendButton`, `SettingsButton`, `Split\Button`.

### Меню кнопки

```php
use Bitrix\UI\Buttons\JsHandler;

Button::create([
    'text' => 'Экспорт',
    'systemMenu' => ['items' => [
        ['id' => 'csv', 'title' => 'CSV', 'onClick' => new JsHandler('BX.Vendor.Orders.exportCsv')],
    ]],
]);
```

- `systemMenu` — **Since ui 26.600**. Раньше: `'menu' => ['items' => [['text' => 'CSV', 'onclick' => new JsHandler(...)]]]`.
- Нужен непустой `items`, иначе systemMenu отбрасывается с записью в журнал. Приоритет выше menu; `setMenu()` с пунктами удаляет systemMenu; `setSystemMenu(null)` тоже удаляет его.

## Доступ из JS к PHP-кнопке

- `$button->setUniqId('orders-export')` (**Since ui 26.200\***; раньше передай `$button->getUniqId()` в JS), затем `ButtonManager.createByUniqId('orders-export')` либо `ButtonManager.createFromNode(node)`.
- Кнопки панели: `BX.UI.ToolbarManager.getDefaultToolbar().getButton(uniqId)` (`rules/toolbar.md`).
