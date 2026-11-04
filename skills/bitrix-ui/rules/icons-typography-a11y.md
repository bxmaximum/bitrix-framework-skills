# Иконки, типографика, Lottie, доступность

## Иконки (`ui.icon-set`)

JS API ui.icon-set.api.core: Icon, IconHoverMode и константы наборов. Каждому набору нужна также **CSS-зависимость**, иначе иконка пустая.

```js
import { Icon, Outline } from 'ui.icon-set.api.core';
import 'ui.icon-set.outline';

new Icon({ icon: Outline.CHECK_L, size: 24, color: 'var(--ui-color-base-70)' }).renderTo(container);
```

- Набор → CSS: Actions → ui.icon-set.actions, Main → ui.icon-set.main, Outline → ui.icon-set.outline; также Solid, Social, CRM, Editor, ContactCenter, Animated, Special, SmallOutline, Disk/DiskCompact (цвет фиксирован, color игнорируется). Outline, Solid, SmallOutline, Disk — **Since ui 26.200\***; раньше Main, Actions.
- PHP / HTML: `<div class="ui-icon-set --check-l"></div>` + `Extension::load('ui.icon-set.outline')`; CSS-переменные размера / цвета --ui-icon-set__icon-size, --ui-icon-set__icon-color.
- Vue: BIcon из ui.icon-set.api.vue, параметры name, size, color, hoverable, responsive.
- Используй именованные иконки, не копии SVG. Цвета и отступы — переменные CSS ui.design-tokens (--ui-color-*).

## Типографика (`ui.system.typography`, **Since ui 26.200\***)

```js
import { Headline, Text } from 'ui.system.typography';

container.append(Headline.render('Заказы', { size: 'lg', tag: 'h2' }), Text.render(order.comment, { size: 'sm' }));
```

Текст вставляется как текстовое содержимое, безопасно для данных пользователя. Headline требует size (xl|lg|md|sm|xs); Text — 2xl … 4xs. Vue: ui.system.typography.vue. Раньше ui.fonts.opensans + свой CSS.

## Lottie (`ui.lottie`)

```js
import { Lottie } from 'ui.lottie'; // либо BX.UI.Lottie

const animation = Lottie.loadAnimation({
    container: node,                     // узел уже в DOM
    path: '/local/assets/loader.json',   // либо animationData: {...}, только один источник
    renderer: 'svg', loop: true, autoplay: true,
});
// при уничтожении:
animation.destroy();
```

`AnimationItem`: `play()`, `stop()`, `pause()`, `setSpeed()`, `goToAndStop()`, `addEventListener('complete' | 'loopComplete' | 'data_failed', fn)`.

## Доступность (`ui.a11y`, **Since ui 26.500\***)

| Задача | Экспорт |
| --- | --- |
| Удержать Tab внутри модального окна | FocusTrap |
| Восстановить фокус после закрытия / перерисовки | FocusMonitor, запускается при импорте |
| Стрелки / Home / End внутри виджета | FocusZone |
| Фокус на следующий / предыдущий доступный элемент | FocusNavigator |
| Озвучить текст без перемещения фокуса | LiveAnnouncer |
| Проверка видимости / фокусируемости, способ ввода | InteractivityChecker, InputModalityTracker |
| Скрыть визуально, сохранить для экранного диктора | VisuallyHidden |

```js
import { FocusTrap, LiveAnnouncer } from 'ui.a11y';

const trap = new FocusTrap(dialogNode);
trap.activate(); // при закрытии trap.deactivate()
LiveAnnouncer.announce('Сохранено', 'polite'); // либо assertive
```

- Popup принимает focusTrap: true; слайдеры SidePanel всегда удерживают фокус, focusTrap принимает только настройки (**Since main 26.600\***).
- Флаги ui → a11y (restoreLostFocus, useFocusTrapInDialogs, `bitrix-settings`) на обычной установке false. В своих диалогах явно включай FocusTrap.
- На старом ui управляй фокусом вручную: element.focus(), область aria-live.

## Ресурсы

- Из CSS /local не ссылайся на картинки и CSS ядра (`/bitrix/js/…`, `/bitrix/panel/…`): пути меняются, main 26.800 заменил PNG-спрайты на WebP. Скопируй ресурс в свой модуль.
- Не строй шаблон /local на ui.bootstrap4: JS Bootstrap 4.6.2, CSS 5.0.0-alpha1 (ui 26.600). Поставляй свою версию Bootstrap.

## Иллюстрации — Since ui 26.700

ui.system.illustration экспортирует Illustration и IllustrationName. Например: `new Illustration({ name: IllustrationName.EMPTY_SEARCH, size: 120, ariaLabel: 'Ничего не найдено' }).renderTo(container)`. Используй имя из enum, задавай ariaLabel для смысловой картинки; пустой ariaLabel оставляет её декоративной. При удалении — `destroy()`. На старом ui поставляй свою иллюстрацию в ресурсах модуля.
