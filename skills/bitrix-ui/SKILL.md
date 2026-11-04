---
name: bitrix-ui
description: 'Интерфейс Битрикс из расширений ui/main и PHP-фасадов: Popup, SidePanel, Toolbar, Grid и фильтр, EntitySelector, загрузчик. Используй для страниц портала, административных форм и шаблонов.'
---

# Интерфейс Битрикс

Baseline: main 23.0+ · Verified: ui 26.700.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

| Задача | Читать |
| --- | --- |
| `main.popup`, `Menu`, страницы боковой панели | `rules/popups-sidepanel.md` |
| Диалог, подтверждение, меню, выбор флажков | `rules/dialogs-menus.md` |
| Кнопки JS / PHP, меню кнопок | `rules/buttons.md` |
| Шапка страницы: заголовок, кнопки, фильтр | `rules/toolbar.md` |
| Список: грид, фильтр, групповые действия | `rules/grid-filter.md` |
| Выбор пользователей / сущностей, поставщик, каталог | `rules/selectors.md` |
| Разметка форм, поля, выбор значения / цвета | `rules/forms-inputs.md` |
| Сообщения, подсказки, загрузка, форма обратной связи | `rules/feedback.md` |
| Прогресс, список шагов, выполнение Engine-очереди | `rules/progress-steps.md` |
| Счётчики, метки статуса, значки | `rules/counters-labels.md` |
| Стадии, история, текст BBCode | `rules/stageflow-timeline.md` |
| Поле загрузки файла | `rules/uploader.md` |
| Просмотр файлов | `rules/viewer.md` |
| Иконки, типографика, Lottie, фокус / доступность | `rules/icons-typography-a11y.md` |

## Общие правила

- Загружай по ID: `Extension::load('ui.x')` в PHP, `import { X } from 'ui.x'` в сборке. Не подключай JS/CSS ядра по пути: файлы перемещаются.
- Новый дизайн: `ui.system.*` (**Since ui 26.200\***). На старом ядре: `main.popup`, `ui.alerts`, `ui.dialogs.messagebox`. Легенда Since: `bitrix-framework`.
- Строковые `content`, `text`, `html`, `description` и PHP-слоты часто принимают сырой HTML: передавай DOM-узлы либо экранируй (`Text.encode()`, `htmlspecialcharsbx()`).
- Виджеты выводят и хранят состояние интерфейса. Данные, права и CSRF проверяет сервер (`bitrix-controllers`).
- Только публичный API: без полей `_private` и переопределения внутренних методов.
- Vue: виджеты вне реактивного состояния (`markRaw()`), см. `bitrix-vue`.
