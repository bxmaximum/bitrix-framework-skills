# Формы и поля

| Задача | Использовать |
| --- | --- |
| Стили серверных input/select/textarea | `ui.forms`, только CSS, ui-ctl* |
| Сетка «подпись — значение», сворачиваемые блоки | `ui.layout-form`: CSS + LayoutForm, включает ui.forms |
| JS-поле с подписью, ошибкой, очисткой / копированием, метками | `ui.system.input` → Input, PasswordInput, InputSize, InputDesign (**Since ui 26.200\***; раньше разметка ui.forms) |
| Метка / chip | `ui.system.chip` → Chip, ChipDesign, ChipSize (**Since ui 26.200\***; раньше ui.label) |
| Одно значение фиксированного списка | `ui.select` → Select, отдельно загрузи ui.forms |
| Цвет из палитры | color_picker, глобальный BX.ColorPicker |
| Пользователи, сущности, серверный поиск | ui.entity-selector (`rules/selectors.md`) |

## Разметка ui.forms

Обёртка .ui-ctl, внутри .ui-ctl-element; модификаторы задавай обёртке. Импортировать нечего.

```html
<div class="ui-ctl-container">
    <div class="ui-ctl-top"><div class="ui-ctl-title">Status</div></div>
    <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100">
        <div class="ui-ctl-after ui-ctl-icon-angle"></div>
        <select class="ui-ctl-element" name="STATUS"><option value="new">New</option></select>
    </div>
    <div class="ui-ctl-bottom">Ошибка либо подсказка</div>
</div>
```

- Размер ui-ctl-lg|md|sm|xs (48/40/32/26 px); ширина ui-ctl-w100|w75|w50|w33|w25, ui-ctl-wa — авто.
- Типы: ui-ctl-textarea (+ ui-ctl-no-resize), ui-ctl-checkbox|radio, ui-ctl-file-drop|file-btn|file-link (label + скрытый input file), ui-ctl-multiple-select, ui-ctl-date|time|datetime (без виджета календаря).
- Иконки: ui-ctl-before|after, отступ обёртки ui-ctl-before-icon|after-icon; ui-ctl-icon-search|clear|calendar|…. Иконки игнорируют нажатия: интерактивная должна быть button (`<button class="ui-ctl-after ui-ctl-icon-clear">`).
- Ошибки: --error у .ui-ctl-element, ui-ctl-warning / ui-ctl-success у .ui-ctl-container (цвета .ui-ctl-bottom). Валидации нет, классы переключай сам.
- ui-ctl-disabled меняет только вид, значение отправляется. Для блокировки добавь атрибут disabled.

## `ui.layout-form`

.ui-form > .ui-form-row > .ui-form-label + .ui-form-content; .ui-form-line — все строки горизонтально, .ui-form-section, .ui-form-row-inline. Подсказки подписей требуют отдельной загрузки ui.hint.

```js
import { LayoutForm } from 'ui.layout-form';

// разметка: <div data-form-row-hidden><input type="checkbox" class="ui-ctl-element">…</div>
//           <div class="ui-form-row-hidden">…скрытые строки…</div> — следующий соседний узел
const form = new LayoutForm({ container: formNode }); // один обход; экземпляр на контейнер
form.subscribe('onToggle', (event) => { const { checkbox } = event.getData(); syncDisabled(checkbox.checked); });
```

Checkbox не вызывает change: нажатия перехвачены, используй onToggle. Контрол без checkbox ломает инициализацию всех блоков. Скрытые поля отправляются.

## `ui.system.input`

```js
import { Input, InputSize } from 'ui.system.input';

const input = new Input({ label: 'Заголовок', value: order.title, size: InputSize.Md, withClear: true, onInput: () => {} });
container.append(input.render());
input.setError('Обязательное поле'); // getValue(), setValue(), focus(), destroy()
```

Атрибута name нет: читай `getValue()` либо копируй в скрытый input перед отправкой.

## `ui.select`

```js
import { Select } from 'ui.select'; // глобальный BX.Ui.Select, Ui с маленькой i

const select = new Select({ options: [{ label: 'Новый', value: 'new' }], value: 'new', isSearchable: true });
select.renderTo(container); // очищает контейнер
select.subscribe('update', (event) => hidden.value = event.getData());
```

Одиночный выбор, без сервера, `setOptions()`/`destroy()` нет — пересоздай. update вызывается и при каждом закрытии с выбором: убирай повторы. Скрытого input name нет.

## `color_picker`

Расширение main, только глобальный объект, без import: `Extension::load('color_picker')`.

```js
const picker = new BX.ColorPicker({
    bindElement: button,
    selectedColor: '#2fc6f6',
    onColorSelected: (color) => { input.value = color; }, // #rrggbb в нижнем регистре
});
picker.open();
```

popupOptions читаются в конструкторе. colors: string[][] задаёт палитру; allowCustomColor: false, colorPreview: false скрывают нижнюю панель.
