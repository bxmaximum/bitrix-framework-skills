# Forms and inputs

| Need | Use |
| --- | --- |
| Server-rendered `input`/`select`/`textarea` styling | `ui.forms` (CSS only, `ui-ctl*`) |
| "label — value" form grid, collapsible blocks | `ui.layout-form` (CSS + `LayoutForm`; includes `ui.forms`) |
| JS-built field with label, error, clear/copy icons, chips | `ui.system.input` → `Input`, `PasswordInput`, `InputSize`, `InputDesign` (**Since ui 26.200\***; older: `ui.forms` markup) |
| Tag / chip | `ui.system.chip` → `Chip`, `ChipDesign`, `ChipSize` (**Since ui 26.200\***; older: `ui.label`) |
| Single value from a fixed list | `ui.select` → `Select` + load `ui.forms` |
| Color from a palette | `color_picker` (global `BX.ColorPicker`) |
| Users / entities / server search | `ui.entity-selector` (`rules/selectors.md`) |

## `ui.forms` markup

Wrapper `.ui-ctl` + element `.ui-ctl-element`; every modifier goes on the wrapper. There is nothing to import.

```html
<div class="ui-ctl-container">
    <div class="ui-ctl-top"><div class="ui-ctl-title">Status</div></div>
    <div class="ui-ctl ui-ctl-after-icon ui-ctl-dropdown ui-ctl-w100">
        <div class="ui-ctl-after ui-ctl-icon-angle"></div>
        <select class="ui-ctl-element" name="STATUS"><option value="new">New</option></select>
    </div>
    <div class="ui-ctl-bottom">Error or hint text</div>
</div>
```

- Size `ui-ctl-lg|md|sm|xs` (48/40/32/26 px); width `ui-ctl-w100|w75|w50|w33|w25`, `ui-ctl-wa` (auto).
- Types: `ui-ctl-textarea` (+ `ui-ctl-no-resize`), `ui-ctl-checkbox|radio`, `ui-ctl-file-drop|file-btn|file-link` (`<label>` + hidden file input), `ui-ctl-multiple-select`, `ui-ctl-date|time|datetime` (no picker included).
- Icons: element `ui-ctl-before|after` + wrapper padding `ui-ctl-before-icon|after-icon`; `ui-ctl-icon-search|clear|calendar|…`. Icons ignore clicks: a clickable one must be a `<button>` (`<button class="ui-ctl-after ui-ctl-icon-clear">`).
- Errors: `--error` on `.ui-ctl-element`, `ui-ctl-warning` / `ui-ctl-success` on `.ui-ctl-container` (colors `.ui-ctl-bottom`). No validation logic: toggle classes yourself.
- `ui-ctl-disabled` is visual only (value still submitted); add the `disabled` attribute to block it.

## `ui.layout-form`

`.ui-form` > `.ui-form-row` > `.ui-form-label` + `.ui-form-content`; `.ui-form-line` (all rows horizontal), `.ui-form-section`, `.ui-form-row-inline`. Hints in labels need `ui.hint` loaded separately.

```js
import { LayoutForm } from 'ui.layout-form';

// markup: <div data-form-row-hidden><input type="checkbox" class="ui-ctl-element">…</div>
//         <div class="ui-form-row-hidden">…collapsed rows…</div>   ← next sibling
const form = new LayoutForm({ container: formNode }); // scans once; one instance per container
form.subscribe('onToggle', (event) => { const { checkbox } = event.getData(); syncDisabled(checkbox.checked); });
```

The checkbox never fires `change` (clicks are intercepted): use `onToggle`. A control without a checkbox breaks init of all blocks. Collapsed fields are still submitted.

## `ui.system.input`

```js
import { Input, InputSize } from 'ui.system.input';

const input = new Input({ label: 'Title', value: order.title, size: InputSize.Md, withClear: true, onInput: () => {} });
container.append(input.render());
input.setError('Required');       // getValue(), setValue(), focus(), destroy()
```

No `name` attribute: read `getValue()` or mirror into a hidden input before submit.

## `ui.select`

```js
import { Select } from 'ui.select'; // global BX.Ui.Select (lowercase "Ui")

const select = new Select({ options: [{ label: 'New', value: 'new' }], value: 'new', isSearchable: true });
select.renderTo(container);        // clears container
select.subscribe('update', (event) => hidden.value = event.getData());
```

Single select, no server loading, no `setOptions()`/`destroy()` (recreate). `update` also fires on every close with a selection: dedupe. No hidden `<input name>`.

## `color_picker`

Main core extension, global only (no `import`): `Extension::load('color_picker')`.

```js
const picker = new BX.ColorPicker({
    bindElement: button,
    selectedColor: '#2fc6f6',
    onColorSelected: (color) => { input.value = color; }, // lowercase #rrggbb
});
picker.open();
```

`popupOptions` are read only in the constructor. `colors: string[][]` sets the palette; `allowCustomColor: false, colorPreview: false` hide the bottom panel.
