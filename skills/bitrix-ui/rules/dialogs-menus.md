# Dialogs, confirms, menus

| Need | Extension → export (global) | Since |
| --- | --- | --- |
| Modal: title, content, buttons | `ui.system.dialog` → `Dialog` (`BX.UI.System.Dialog`) | ui 26.200* |
| Confirm / alert box | `ui.dialogs.messagebox` → `MessageBox`, `MessageBoxButtons` (`BX.UI.Dialogs`) | baseline |
| Dropdown / context menu, new design | `ui.system.menu` → `Menu`, `MenuItemDesign` (`BX.UI.System.Menu`) | ui 26.200* |
| Anchored popover (floating-ui) | `ui.system.popover` → `Popover`; Vue `BPopover` from `ui.system.popover.vue` | ui 26.600 |
| User reorders / hides items, saves | `ui.menu-configurable` → `Menu` (`BX.UI.MenuConfigurable.Menu`) | baseline |

Fallback on older ui: `Popup` / `Menu` from `main.popup` (`rules/popups-sidepanel.md`).

## Dialog

```js
import { Dialog } from 'ui.system.dialog';
import { Button, ButtonColor } from 'ui.buttons'; // not in the dialog's rel: load both

const dialog = new Dialog({
    title: 'Archive order',       // rendered as text
    content: formNode,            // HTMLElement only
    rightButtons: [new Button({
        text: 'Archive',
        color: ButtonColor.PRIMARY,
        onclick: () => archive().then(() => dialog.hide()),
    })],
    hasOverlay: true,             // default false
    events: { onAfterHide: () => {} },
});
dialog.show();
```

Other options: `subtitle`, `leftButtons`, `centerButtons`, `hasCloseButton` (true), `closeByEsc` / `closeByClickOutside` (true), `width`, `hasVerticalPadding` / `hasHorizontalPadding` (true), `background: DialogBackground.vibrant`, `disableScrolling`. Events: `onShow`, `onAfterShow`, `onHide`, `onAfterHide`. Methods `setTitle()`, `setContent(node)`, `set*Buttons()`.

## MessageBox

```js
import { Text } from 'main.core';
import { MessageBox, MessageBoxButtons } from 'ui.dialogs.messagebox';

MessageBox.confirm(
    `Delete ${Text.encode(name)}?`,           // message is HTML
    () => api.remove(id).then(() => true),  // resolved Promise or true closes the box
);
MessageBox.show({ title: 'Export', message: node, buttons: MessageBoxButtons.YES_NO, onYes: (box) => { box.close(); } });
MessageBox.alert('Done');
```

- Callbacks get `(messageBox, button, event)`. Return `true` or a Promise to close, `false` to stay open. Returning nothing leaves the box open with the button disabled: call `box.close()` yourself.
- Signatures: `confirm(message, [title,] onOk, okCaption, onCancel, cancelCaption)`, `alert(message, [title,] onOk, okCaption)`.
- Buttons: `OK`, `CANCEL`, `YES`, `NO`, `OK_CANCEL`, `YES_NO`, `YES_CANCEL`, `YES_NO_CANCEL`; callbacks `onOk`, `onCancel`, `onYes`, `onNo`.

## System menu

```js
import { Menu, MenuItemDesign } from 'ui.system.menu';

const menu = new Menu({
    bindElement: button,          // the control itself: the menu sets aria-expanded on it
    items: [
        { id: 'edit', title: 'Edit', onClick: () => edit() },
        { id: 'delete', title: 'Delete', design: MenuItemDesign.Alert, onClick: () => remove() },
    ],
});
menu.show();
```

Item keys: `title`, `subtitle` (escaped), `icon`, `isSelected`, `disabled` (still clickable, aria-disabled), `subMenu`, `sectionCode` + menu `sections`, `counter`. `updateItems()`, `close()`, `destroy()` (call it when the trigger node outlives the menu). Buttons get it via `systemMenu` (`rules/buttons.md`).

## Popover

```js
import { Popover, PopoverPosition } from 'ui.system.popover';

const popover = new Popover({ content: node, target: button, positioning: { position: PopoverPosition.Bottom } });
popover.show(); // hide(), destroy(), setTarget(), setPositioning(), adjustPosition()
```

String `content` is parsed as HTML. Hiding does not return focus: move it back to the trigger when content is interactive.

## Configurable menu

```js
import { Menu as ConfigurableMenu } from 'ui.menu-configurable'; // name clashes with main.popup Menu

const menu = new ConfigurableMenu({ items: [{ id: 'price', text: 'Price', isHidden: false }], maxVisibleItems: 5 });
const result = await menu.open(button);
if (!result.isCanceled) { await saveColumns(result.items); } // it does not persist anything
```

Item `text` is escaped, `html` is raw; `onclick`, `href`, submenus are ignored. Give `id` only for a menu you do not recreate (instances are cached by id).
