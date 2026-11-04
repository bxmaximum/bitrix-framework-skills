# Счётчики, метки, значки

| Задача | JS | PHP |
| --- | --- | --- |
| Числовой значок: непрочитанные, новые | `ui.cnt` → `Counter`, `CounterColor`, `CounterSize`, `CounterStyle` (`BX.UI.Counter`) | `Bitrix\UI\Counter\Counter` **Since ui 26.200\*** |
| Метка статуса, новый дизайн | `ui.system.label` → `Label`, `LabelStyle`, `LabelSize`, `LabelIcon` (`BX.UI.System.Label`) **Since ui 26.200\*** | `Bitrix\UI\Public\System\Label\Label` **Since ui 26.500\*** |
| Цветная метка, ссылка, состояние загрузки / галочка | `ui.label` → `Label`, `LabelColor`, `LabelSize` (`BX.UI.Label`) | — |
| Удаляемая метка / метка с меню | `ui.system.chip` (`rules/forms-inputs.md`) | `Bitrix\UI\Public\System\Chip\Chip` **Since ui 26.500\*** |

`ui.counter` — псевдоним `ui.cnt` для обхода AdBlock: загружай любой, импорт из `ui.cnt`. На старом ядре — `ui.label` вместо `ui.system.label`; метки и chips выводи JS вместо PHP-классов.

## Счётчик

```js
import { Counter, CounterColor } from 'ui.cnt';

const counter = new Counter({ value: 5, maxValue: 99, color: CounterColor.DANGER }); // выше maxValue показывает 99+
counter.renderTo(node);
counter.update(12); // перерисовывает, в отличие от setValue()
```

- Параметры: `size` (`SMALL|MEDIUM|LARGE`), `border`, `isDouble` + `secondaryColor`, `animate`. Цвета: `DANGER`, `WARNING`, `SUCCESS`, `PRIMARY`, `GRAY`, `LIGHT`, `WHITE`, `DARK`, `THEME`.
- Air (**Since ui 26.200\***): `useAirDesign: true` + `style: CounterStyle.FILLED_ALERT` (color игнорируется), `hideIfZero`, `usePercentSymbol` (игнорирует maxValue), `node` (готовая разметка), `id`. В Air `animate: true` делает `update()` пустой операцией.
- Методы: `getValue()` (возможна строка 99+), `getRealValue()`, `setMaxValue()`, `setColor()`, `setStyle()`, `show()`, `hide()`, `destroy()`.
- Vue: `Counter` из `ui.vue3.components.counter` (компонент BCounter, всегда Air; параметры value, maxValue, style, size, border) **Since ui 26.200\***. Раньше создавай ui.cnt Counter в mounted().

PHP-вывод (**Since ui 26.200\***; раньше разметка `<div class="ui-counter ui-counter-danger">` либо JS):

```php
use Bitrix\Main\UI\Extension;
use Bitrix\UI\Counter\Counter;
use Bitrix\UI\Counter\CounterStyle;

Extension::load('ui.counter'); // render() не загружает расширение
echo (new Counter(useAirDesign: true, style: CounterStyle::FILLED_ALERT, value: $unread, id: 'orders-unread'))->render();
```

- Стандартный стиль различается: PHP FILLED_ALERT, JS FILLED.
- Обновление серверной разметки: `Counter.updateCounterNodeValue(node, 7)` либо `Counter.initFromCounterNode(node)` → Counter|null (восстанавливает только air/style/color/size/value) **Since ui 26.200\***. Раньше заменяй узел новым Counter.
- У кнопок свои счётчики (`rules/buttons.md`).

## Системная метка

```js
import { Label, LabelStyle, LabelSize } from 'ui.system.label';

container.append(new Label({ value: 'Оплачено', style: LabelStyle.FILLED_SUCCESS, size: LabelSize.SM }).render());
```

Стили: `FILLED*`, `TINTED`, `TINTED_SUCCESS|WARNING|NO_ACCENT`, `OUTLINE_NO_ACCENT`, `COLLAB`; `TINTED_ALERT`, `TINTED_VIOLET`, `*_BITRIX_GPT` — **Since ui 26.500\***, только JS; раньше TINTED_*. Методы: `setValue()`, `setStyle()`, `setSize()`, `setIcon()`, `setBordered()`, `destroy()`.

```php
use Bitrix\UI\Public\System\Label\Label;
use Bitrix\UI\Public\System\Label\Size;
use Bitrix\UI\Public\System\Label\Style;

echo Label::create(['value' => $status]) // value экранируется; render() загружает ui.system.label
    ->setStyle(Style::TINTED_SUCCESS)
    ->setSize(Size::SM)
    ->render();
```

`style`, `size`, `icon` принимают enum (`Style`, `Size`, `Icon`). Строка в массиве молча игнорируется, в setter вызывает TypeError.

## `ui.label`

```js
import { Label, LabelColor, LabelSize } from 'ui.label';

new Label({ text: 'Черновик', color: LabelColor.LIGHT_BLUE, size: LabelSize.SM, fill: true }).render();
```

Объект параметров обязателен. link выбирается при построении: последующий `setLink()` не перерисовывает. status loading / check показывает ожидание / галочку. Метода destroy нет.
