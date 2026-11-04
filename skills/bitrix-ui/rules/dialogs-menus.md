# Диалоги, подтверждения, меню

| Задача | Расширение → экспорт (глобальный объект) | Since |
| --- | --- | --- |
| Модальное окно: заголовок, содержимое, кнопки | `ui.system.dialog` → `Dialog` (`BX.UI.System.Dialog`) | ui 26.200* |
| Подтверждение / сообщение | `ui.dialogs.messagebox` → `MessageBox`, `MessageBoxButtons` (`BX.UI.Dialogs`) | baseline |
| Выпадающее / контекстное меню, новый дизайн | `ui.system.menu` → `Menu`, `MenuItemDesign` (`BX.UI.System.Menu`) | ui 26.200* |
| Привязанный всплывающий блок (floating-ui) | `ui.system.popover` → `Popover`; Vue BPopover из `ui.system.popover.vue` | ui 26.600 |
| Перестановка / скрытие пунктов пользователем | `ui.menu-configurable` → `Menu` (`BX.UI.MenuConfigurable.Menu`) | baseline |

Старое ui: Popup / Menu из main.popup (`rules/popups-sidepanel.md`).

## Модальное окно Dialog

```js
import { Dialog } from 'ui.system.dialog';
import { Button, ButtonColor } from 'ui.buttons'; // нет в rel диалога: загрузи оба

const dialog = new Dialog({
    title: 'Архивировать заказ',  // выводится текстом
    content: formNode,            // только HTMLElement
    rightButtons: [new Button({
        text: 'Архивировать',
        color: ButtonColor.PRIMARY,
        onclick: () => archive().then(() => dialog.hide()),
    })],
    hasOverlay: true,             // по умолчанию false
    events: { onAfterHide: () => {} },
});
dialog.show();
```

Другие параметры: subtitle, leftButtons, centerButtons, hasCloseButton (true), closeByEsc / closeByClickOutside (true), width, hasVerticalPadding / hasHorizontalPadding (true), background: DialogBackground.vibrant, disableScrolling. События: onShow, onAfterShow, onHide, onAfterHide. Методы: `setTitle()`, setContent(node), set*Buttons().

## Подтверждение MessageBox

```js
import { Text } from 'main.core';
import { MessageBox, MessageBoxButtons } from 'ui.dialogs.messagebox';

MessageBox.confirm(
    `Удалить ${Text.encode(name)}?`, // сообщение — HTML
    () => api.remove(id).then(() => true), // успешный Promise либо true закрывает окно
);
MessageBox.show({ title: 'Экспорт', message: node, buttons: MessageBoxButtons.YES_NO, onYes: (box) => { box.close(); } });
MessageBox.alert('Готово');
```

- Аргументы обработчиков: `(messageBox, button, event)`. true либо Promise закрывает, false оставляет. Без возврата окно остаётся с заблокированной кнопкой: вызови box.close() сам.
- Сигнатуры: `confirm(message, [title,] onOk, okCaption, onCancel, cancelCaption)`, `alert(message, [title,] onOk, okCaption)`.
- Кнопки: OK, CANCEL, YES, NO, OK_CANCEL, YES_NO, YES_CANCEL, YES_NO_CANCEL; обработчики onOk, onCancel, onYes, onNo.

## Системное меню

```js
import { Menu, MenuItemDesign } from 'ui.system.menu';

const menu = new Menu({
    bindElement: button, // сам контрол: меню устанавливает ему aria-expanded
    items: [
        { id: 'edit', title: 'Изменить', onClick: () => edit() },
        { id: 'delete', title: 'Удалить', design: MenuItemDesign.Alert, onClick: () => remove() },
    ],
});
menu.show();
```

Пункт: title, subtitle (экранируются), icon, isSelected, disabled (всё ещё нажимается, aria-disabled), subMenu, sectionCode + sections меню, counter. `updateItems()`, close(), `destroy()` — вызови, когда узел открытия живёт дольше меню. Для кнопок — systemMenu (`rules/buttons.md`).

## Всплывающий блок Popover

```js
import { Popover, PopoverPosition } from 'ui.system.popover';

const popover = new Popover({ content: node, target: button, positioning: { position: PopoverPosition.Bottom } });
popover.show(); // hide(), destroy(), setTarget(), setPositioning(), adjustPosition()
```

Строковый content разбирается как HTML. Скрытие не возвращает фокус: для интерактивного содержимого верни его на элемент открытия.

## Настраиваемое меню

```js
import { Menu as ConfigurableMenu } from 'ui.menu-configurable'; // имя совпадает с Menu main.popup

const menu = new ConfigurableMenu({ items: [{ id: 'price', text: 'Цена', isHidden: false }], maxVisibleItems: 5 });
const result = await menu.open(button);
if (!result.isCanceled) { await saveColumns(result.items); } // само ничего не сохраняет
```

text экранируется, html — сырой. onclick, href и подменю игнорируются. Задавай id только меню, которое не пересоздаёшь: экземпляры кешируются по id.

## Список флажков ui.dialogs.checkbox-list

```js
import { CheckboxList } from 'ui.dialogs.checkbox-list';

const dialog = new CheckboxList({
    categories: [{ key: 'main', title: 'Основные' }],
    options: [{ id: 'NAME', title: 'Название', categoryKey: 'main', value: true, defaultValue: true }],
    params: { destroyPopupAfterClose: false },
    events: { onApply: (event) => saveColumns(event.getData().fields) },
});
dialog.show();
```

- fields — массив выбранных ID; сохранение выполняет обработчик. При разрешённом переименовании подписи лежат в data.titles, отдельно от fields.
- closeAfterApply задаётся **внутри params**. По умолчанию окно закрывается и уничтожается; destroyPopupAfterClose: false позволяет повторное открытие без пересоздания разметки. `destroy()` уничтожает окно, последующий `show()` строит его заново.
- selectOption(id, false) снимает флажок; `getSelectedOptions()` возвращает выбор. saveColumns(ids) отмечает перечисленное и вызывает `apply()`, остальные флажки не снимает.
- locked и видимость флажков не заменяют серверную проверку допустимых ID. ui.dialogs.messagebox не входит в зависимости; для собственного подтверждения подключай отдельно.
