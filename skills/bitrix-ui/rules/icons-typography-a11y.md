# Icons, typography, Lottie, accessibility

## Icons (`ui.icon-set`)

JS API `ui.icon-set.api.core` (`Icon`, `IconHoverMode` and the set constants). Every set also needs its **CSS extension**, or the icon renders empty.

```js
import { Icon, Outline } from 'ui.icon-set.api.core';
import 'ui.icon-set.outline';

new Icon({ icon: Outline.CHECK_L, size: 24, color: 'var(--ui-color-base-70)' }).renderTo(container);
```

- Sets → CSS extension: `Actions` → `ui.icon-set.actions`, `Main` → `ui.icon-set.main`, `Outline` → `ui.icon-set.outline`, also `Solid`, `Social`, `CRM`, `Editor`, `ContactCenter`, `Animated`, `Special`, `SmallOutline`, `Disk`/`DiskCompact` (fixed colors, `color` ignored). `Outline`, `Solid`, `SmallOutline`, `Disk` are **Since ui 26.200\*** (older: `Main`, `Actions`).
- PHP / static HTML: `<div class="ui-icon-set --check-l"></div>` + `Extension::load('ui.icon-set.outline')`; size and color via CSS vars `--ui-icon-set__icon-size`, `--ui-icon-set__icon-color`.
- Vue: `BIcon` from `ui.icon-set.api.vue` (props `name`, `size`, `color`, `hoverable`, `responsive`).
- Use named icons, not copied SVG. Colors and spacing come from `ui.design-tokens` CSS vars (`--ui-color-*`).

## Typography (`ui.system.typography`, **Since ui 26.200\***)

```js
import { Headline, Text } from 'ui.system.typography';

container.append(Headline.render('Orders', { size: 'lg', tag: 'h2' }), Text.render(order.comment, { size: 'sm' }));
```

Text goes in as text content (safe for user data). `Headline` needs `size` (`xl|lg|md|sm|xs`); `Text` sizes `2xl` … `4xs`. Vue: `ui.system.typography.vue`. Older ui: `ui.fonts.opensans` + your CSS.

## Lottie (`ui.lottie`)

```js
import { Lottie } from 'ui.lottie'; // or BX.UI.Lottie

const animation = Lottie.loadAnimation({
    container: node,                     // must be in the DOM
    path: '/local/assets/loader.json',   // or animationData: {...}, one source only
    renderer: 'svg', loop: true, autoplay: true,
});
// on teardown:
animation.destroy();
```

`AnimationItem`: `play()`, `stop()`, `pause()`, `setSpeed()`, `goToAndStop()`, `addEventListener('complete' | 'loopComplete' | 'data_failed', fn)`.

## Accessibility (`ui.a11y`, **Since ui 26.500\***)

| Need | Export |
| --- | --- |
| Keep Tab inside a modal | `FocusTrap` |
| Restore focus after close / redraw | `FocusMonitor` (starts on import) |
| Arrow / Home / End keys inside a widget | `FocusZone` |
| Move focus to next / previous focusable | `FocusNavigator` |
| Announce text without moving focus | `LiveAnnouncer` |
| Visible / focusable checks, input type | `InteractivityChecker`, `InputModalityTracker` |
| Hide visually, keep for screen readers | `VisuallyHidden` |

```js
import { FocusTrap, LiveAnnouncer } from 'ui.a11y';

const trap = new FocusTrap(dialogNode);
trap.activate();   // on close: trap.deactivate()
LiveAnnouncer.announce('Saved', 'polite'); // or 'assertive'
```

- `Popup` accepts `focusTrap: true`; `SidePanel` sliders always trap focus (`focusTrap` takes only options) (**Since main 26.600\***).
- Global flags `ui` → `a11y` (`restoreLostFocus`, `useFocusTrapInDialogs`, `bitrix-settings`) are `false` on a regular install: don't rely on them, activate `FocusTrap` in your own dialogs.
- Older ui: manage focus manually (`element.focus()`, `aria-live` region).

## Assets

- Never reference kernel images or CSS paths (`/bitrix/js/…`, `/bitrix/panel/…`) from `/local` CSS: they move (main 26.800 replaced PNG sprites with WebP). Copy the asset into your module.
- Don't build `/local` templates on `ui.bootstrap4`: its JS is Bootstrap 4.6.2, its CSS 5.0.0-alpha1 (ui 26.600). Ship your own Bootstrap.
