# Сообщения, уведомления, подсказки, заглушки

| Задача | Расширение → экспорт (глобальный объект) |
| --- | --- |
| Сообщение в странице, новый дизайн | `ui.system.alert` → `Alert`, `AlertDesign` (`BX.UI.System.Alert`), **Since ui 26.200\*** |
| Сообщение в странице, все версии | `ui.alerts` → `Alert`, `AlertColor`, `AlertSize`, `AlertIcon` (`BX.UI`) |
| Временное уведомление | `ui.notification` → `BX.UI.Notification.Center` |
| Уведомление рабочего стола / между вкладками с кнопками | `ui.notification-manager` → `Notifier` (`BX.UI.NotificationManager`) |
| Короткая подсказка «?» | `ui.hint` → глобальный BX.UI.Hint; импорт Hint — **Since ui 26.500\*** |
| Карточка пользователя при наведении | `ui.tooltip`, атрибут bx-tooltip-user-id |
| Заглушка загрузки | `ui.system.skeleton` → Line, Circle, renderSkeleton, **Since ui 26.200\***; раньше main.loader |

Краткий результат — уведомление; обязательная информация / ошибка — сообщение в странице; решение — MessageBox (`rules/dialogs-menus.md`).

## Сообщения

```js
import { Alert, AlertDesign } from 'ui.system.alert';

const alert = new Alert({ design: AlertDesign.tintedSuccess, content: 'Сохранено', hasCloseButton: true });
container.append(alert.render()); // строковое содержимое выводится текстом
```

AlertDesign: tinted, tintedSuccess, tintedWarning, tintedAlert. На старом ui:

```js
import { Alert, AlertColor } from 'ui.alerts';

new Alert({ text: Text.encode(message), color: AlertColor.DANGER, closeBtn: true }).renderTo(container);
```

text в ui.alerts вставляется через innerHTML: экранируй. Цвета: DEFAULT, DANGER, SUCCESS, WARNING, PRIMARY, INFO.

## Уведомления

```js
BX.UI.Notification.Center.notify({
    content: Text.encode(`Заказ ${number} сохранён`), // HTML
    autoHideDelay: 5000, // по умолчанию 8000
    category: 'vendor-orders', // та же category/id обновляет показанное уведомление
    actions: [{ title: 'Открыть', events: { click: (event, balloon) => { openOrder(); balloon.close(); } } }],
});
```

- position: top-left|top-center|top-right|bottom-left|bottom-center|bottom-right.
- `import { Center } from 'ui.notification'` — **Since ui 26.500\***; глобальный объект работает и раньше.

## Подсказки

```html
<span class="ui-hint" data-hint="Обычный текст с экранированием"></span>
<span data-hint="<b>HTML</b>" data-hint-html></span> <!-- сырой HTML: только доверенное содержимое -->
```

ui.hint инициализирует .ui-hint при загрузке страницы. Для других [data-hint] и поздней разметки вызови BX.UI.Hint.init(container). Атрибуты: data-hint-no-icon, data-hint-center, data-hint-interactivity.

## Карточка пользователя

`Extension::load('ui.tooltip')`, затем `<a href="…" bx-tooltip-user-id="<?= (int)$userId ?>">`. Делегированный mouseover работает и с поздним DOM; на Android/iOS карточки нет.

- Без опции socialnetwork:allow_tooltip = Y расширение ничего не загружает. Стандартной карточке нужен socialnetwork.
- bx-tooltip-params — JSON (`{"entityType":"LOG_ENTRY","entityId":1}`), экранируй; некорректный JSON ломает карточку.
- bx-tooltip-loader — адрес своего обработчика; ответ вставляется как HTML и выполняется. Не собирай адрес из пользовательского ввода.
- bx-tooltip-context="b24" показывает мини-профиль Битрикс24 (**Since ui 26.200\***; раньше атрибут игнорируется, стандартная карточка). JS: Tooltip.`disable()` / `enable()` из ui.tooltip. Старый BX.tooltip() (ядро tooltip) — для совместимости.

## Заглушка загрузки

```js
import { Line, Circle } from 'ui.system.skeleton';

container.append(Circle(32), Line(240, 12, 4)); // ширина, высота, радиус в пикселях
```

После получения данных заменяй заглушку содержимым; не оставляй оба узла в DOM.

## Форма обратной связи ui.feedback.form

Form.open() открывает CRM-форму в боковой панели, выбирая подходящую форму серверным `ui.feedback.loadData`. Загрузи ui.feedback.form **и main.sidepanel отдельно**: второй не входит в rel.

```js
import { Form } from 'ui.feedback.form';
import 'main.sidepanel';

Form.open({
    id: 'vendor-feedback',
    title: 'Обратная связь',
    forms: [{ zones: ['ru'], id: 123, lang: 'ru', sec: 'код_формы' }],
    presets: { source: 'vendor.orders' },
});
```

Данные forms возьми из настроек своей CRM-формы, не копируй демонстрационный код. Сервер получает id/title/forms/defaultForm/presets/portalUri, неизвестные поля не передаются. Повторное открытие с тем же id сохраняет параметры первой загрузки; пока панель открыта, другие вызовы игнорируются. Конструктор Form устарел; для нового кода используй статический open(). Не передавай секреты в presets: они уходят в форму.

## Подсказки нового дизайна — Since ui 26.700

ui.system.tooltip выводит строковый content как текст; разметка включается только html: true. Для простого текста достаточно загрузить расширение и задать data-ui-tooltip="Подсказка"; значение атрибута экранируй для HTML. ui.system.hint экспортирует Hint: `new Hint({ tooltipOptions: { content: text } }).render()` даёт кнопку «?». Удаление DOM не уничтожает экземпляр: вызови `destroy()`. ui.system.callout экспортирует Callout для карточки у target с кнопками; `hide()` сохраняет экземпляр, `destroy()` освобождает. На старом ui используй ui.hint / main.popup с проверенным экранированием.
