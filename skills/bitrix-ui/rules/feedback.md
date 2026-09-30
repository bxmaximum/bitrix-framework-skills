# Alerts, toasts, hints, tooltips, skeletons

| Need | Extension → export (global) |
| --- | --- |
| Inline alert, new design | `ui.system.alert` → `Alert`, `AlertDesign` (`BX.UI.System.Alert`) — **Since ui 26.200\*** |
| Inline alert, any version | `ui.alerts` → `Alert`, `AlertColor`, `AlertSize`, `AlertIcon` (`BX.UI`) |
| Toast | `ui.notification` → `BX.UI.Notification.Center` |
| Desktop / cross-tab notification with buttons | `ui.notification-manager` → `Notifier` (`BX.UI.NotificationManager`) |
| "?" hint with short text | `ui.hint` → global `BX.UI.Hint` (`import { Hint }` **Since ui 26.500\***) |
| User profile card on hover | `ui.tooltip` (attribute `bx-tooltip-user-id`) |
| Loading placeholder | `ui.system.skeleton` → `Line`, `Circle`, `renderSkeleton` — **Since ui 26.200\***; older: `main.loader` spinner |

Transient result → toast; something the user must read or fix → alert; a decision → `MessageBox` (`rules/dialogs-menus.md`).

## Alerts

```js
import { Alert, AlertDesign } from 'ui.system.alert';

const alert = new Alert({ design: AlertDesign.tintedSuccess, content: 'Saved', hasCloseButton: true });
container.append(alert.render()); // string content is rendered as text
```

`AlertDesign`: `tinted`, `tintedSuccess`, `tintedWarning`, `tintedAlert`. Older ui:

```js
import { Alert, AlertColor } from 'ui.alerts';

new Alert({ text: Text.encode(message), color: AlertColor.DANGER, closeBtn: true }).renderTo(container);
```

`ui.alerts` `text` is set as `innerHTML`: escape it. Colors: `DEFAULT`, `DANGER`, `SUCCESS`, `WARNING`, `PRIMARY`, `INFO`.

## Toasts

```js
BX.UI.Notification.Center.notify({
    content: Text.encode(`Order ${number} saved`), // HTML
    autoHideDelay: 5000,                           // default 8000
    category: 'vendor-orders',                     // same category/id updates the shown toast
    actions: [{ title: 'Open', events: { click: (event, balloon) => { openOrder(); balloon.close(); } } }],
});
```

- `position`: `top-left|top-center|top-right|bottom-left|bottom-center|bottom-right`.
- `import { Center } from 'ui.notification'` **Since ui 26.500\***; the global works on every version.

## Hints

```html
<span class="ui-hint" data-hint="Plain text, escaped"></span>
<span data-hint="<b>HTML</b>" data-hint-html></span> <!-- raw HTML: trusted content only -->
```

`ui.hint` initializes `.ui-hint` nodes on page load. For other `[data-hint]` nodes or late markup call `BX.UI.Hint.init(container)`. Attributes: `data-hint-no-icon`, `data-hint-center`, `data-hint-interactivity`.

## User tooltip

`Extension::load('ui.tooltip')`, then mark any element: `<a href="…" bx-tooltip-user-id="<?= (int)$userId ?>">`. One delegated `mouseover` covers late DOM; nothing on Android/iOS.

- The extension loads nothing when option `socialnetwork:allow_tooltip` is not `Y`. The default card needs the `socialnetwork` module.
- `bx-tooltip-params` is JSON (`{"entityType":"LOG_ENTRY","entityId":1}`): escape it; broken JSON breaks the card.
- `bx-tooltip-loader` is a custom endpoint whose response is inserted as HTML and executed: never build it from input.
- `bx-tooltip-context="b24"` shows the Bitrix24 mini-profile (**Since ui 26.200\***; older ui ignores it and shows the standard card). JS: `Tooltip.disable()` / `enable()` from `ui.tooltip`. Legacy `BX.tooltip()` (core `tooltip`) is compat only.

## Skeleton

```js
import { Line, Circle } from 'ui.system.skeleton';

container.append(Circle(32), Line(240, 12, 4)); // (width, height, radius) in px
```

Replace the placeholder with real content when data arrives; don't keep both in the DOM.
