# Counters, labels, badges

| Need | JS | PHP |
| --- | --- | --- |
| Numeric badge (unread, new) | `ui.cnt` → `Counter`, `CounterColor`, `CounterSize`, `CounterStyle` (`BX.UI.Counter`) | `Bitrix\UI\Counter\Counter` **Since ui 26.200\*** |
| Status badge, new design | `ui.system.label` → `Label`, `LabelStyle`, `LabelSize`, `LabelIcon` (`BX.UI.System.Label`) **Since ui 26.200\*** | `Bitrix\UI\Public\System\Label\Label` **Since ui 26.500\*** |
| Tag with palette colors, link-label, loading/check status | `ui.label` → `Label`, `LabelColor`, `LabelSize` (`BX.UI.Label`) | — |
| Chip (removable / dropdown tag) | `ui.system.chip` (`rules/forms-inputs.md`) | `Bitrix\UI\Public\System\Chip\Chip` **Since ui 26.500\*** |

`ui.counter` is an alias extension of `ui.cnt` (AdBlock workaround): load either, import from `ui.cnt`. Older kernels: `ui.label` instead of `ui.system.label`; render labels and chips in JS instead of the PHP classes.

## Counter

```js
import { Counter, CounterColor } from 'ui.cnt';

const counter = new Counter({ value: 5, maxValue: 99, color: CounterColor.DANGER }); // > maxValue shows "99+"
counter.renderTo(node);
counter.update(12);                // redraws; setValue() does not
```

- Options: `size` (`SMALL|MEDIUM|LARGE`), `border`, `isDouble` + `secondaryColor`, `animate`. Colors: `DANGER`, `WARNING`, `SUCCESS`, `PRIMARY`, `GRAY`, `LIGHT`, `WHITE`, `DARK`, `THEME`.
- Air design (**Since ui 26.200\***): `useAirDesign: true` + `style: CounterStyle.FILLED_ALERT` (`color` is ignored), `hideIfZero`, `usePercentSymbol` (ignores `maxValue`), `node` (reuse existing markup), `id`. With air, `animate: true` makes `update()` do nothing.
- Methods: `getValue()` (may be `"99+"`), `getRealValue()`, `setMaxValue()`, `setColor()`, `setStyle()`, `show()`, `hide()`, `destroy()`.
- Vue: `import { Counter } from 'ui.vue3.components.counter'` (component `BCounter`, always air; props `value`, `maxValue`, `style`, `size`, `border`) **Since ui 26.200\***; older: mount a `ui.cnt` `Counter` in `mounted()`.

PHP render (**Since ui 26.200\***; older: markup `<div class="ui-counter ui-counter-danger">` or JS):

```php
use Bitrix\Main\UI\Extension;
use Bitrix\UI\Counter\Counter;
use Bitrix\UI\Counter\CounterStyle;

Extension::load('ui.counter');     // render() does not load it
echo (new Counter(useAirDesign: true, style: CounterStyle::FILLED_ALERT, value: $unread, id: 'orders-unread'))->render();
```

- Default style differs: PHP `FILLED_ALERT`, JS `FILLED`.
- Update server-rendered markup from JS: `Counter.updateCounterNodeValue(node, 7)` or `Counter.initFromCounterNode(node)` → `Counter|null` (restores air/style/color/size/value only) **Since ui 26.200\***; older: replace the node with a new `Counter`.
- Buttons have their own counters (`rules/buttons.md`).

## System label

```js
import { Label, LabelStyle, LabelSize } from 'ui.system.label';

container.append(new Label({ value: 'Paid', style: LabelStyle.FILLED_SUCCESS, size: LabelSize.SM }).render());
```

Styles: `FILLED*`, `TINTED`, `TINTED_SUCCESS|WARNING|NO_ACCENT`, `OUTLINE_NO_ACCENT`, `COLLAB`; `TINTED_ALERT`, `TINTED_VIOLET`, `*_BITRIX_GPT` are **Since ui 26.500\*** (JS only; older: `TINTED_*`). Methods: `setValue()`, `setStyle()`, `setSize()`, `setIcon()`, `setBordered()`, `destroy()`.

```php
use Bitrix\UI\Public\System\Label\Label;
use Bitrix\UI\Public\System\Label\Size;
use Bitrix\UI\Public\System\Label\Style;

echo Label::create(['value' => $status])  // value escaped; render() loads ui.system.label
    ->setStyle(Style::TINTED_SUCCESS)
    ->setSize(Size::SM)
    ->render();
```

`style`, `size`, `icon` take enum cases (`Style`, `Size`, `Icon`): a string in the params array is silently ignored, in a setter it throws `TypeError`.

## `ui.label`

```js
import { Label, LabelColor, LabelSize } from 'ui.label';

new Label({ text: 'Draft', color: LabelColor.LIGHT_BLUE, size: LabelSize.SM, fill: true }).render();
```

Options object is required. `link` is chosen once at build (`setLink()` later does not re-render). `status: 'loading' | 'check'` shows a spinner / check. No destroy method.
