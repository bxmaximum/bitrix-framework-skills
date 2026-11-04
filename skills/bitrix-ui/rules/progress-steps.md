# Прогресс и пошаговое выполнение

| Задача | Расширение |
| --- | --- |
| Показать числовой прогресс | `ui.progressbar` → ProgressBar; `ui.progressround` → ProgressRound |
| Показать инструкцию из шагов | `ui.stepbystep` → StepByStep |
| Выполнять серверные шаги через Engine | `ui.stepprocessing` → ProcessManager, ProcessResultStatus, ProcessCallback |

Это разные контракты: индикатор и StepByStep не запускают серверную работу. Расширения есть и в эталоне main 25.100.500; на неизвестном ядре проверь их config.php и экспорт перед использованием.

## Индикаторы

```js
import { ProgressBar } from 'ui.progressbar';
import { ProgressRound } from 'ui.progressround';

const bar = new ProgressBar({ value: 0, maxValue: 100 });
bar.renderTo(container);
bar.update(35);
bar.finish();
// При уничтожении страницы / виджета:
bar.destroy();
```

- ProgressRound использует такой же цикл `renderTo()`/`update()`/`finish()`/`destroy()`. Старое имя BX.UI.Progressround — устаревший псевдоним, новое с заглавной R.
- `setValue()` меняет значение, `update()` также перерисовывает. maxValue должен быть положительным; значения передавай числами, не строками формы.
- infiniteLoading показывает работу неизвестного объёма, но не создаёт задачу и не опрашивает сервер. textBefore/textAfter могут содержать HTML: не передавай сырой пользовательский ввод.

## Список шагов

```js
import { StepByStep } from 'ui.stepbystep';

const steps = new StepByStep({
    target: container,
    content: [{ html: [{ header: { title: 'Проверьте данные' }, node: instructionNode }] }],
});
steps.init();
```

`init()` очищает target и вставляет разметку. Данные content не становятся исполняемой очередью; header.title выводится текстом, node может быть DOM / HTML, hint попадает в атрибут. Для недоверенного текста создай DOM-узел с textContent. Метода `destroy()` у StepByStep нет: убирай контейнер и собственные слушатели.

## Серверная очередь

```js
import { ProcessManager } from 'ui.stepprocessing';

const process = ProcessManager.create({
    id: 'vendor-export',
    controller: 'vendor:reports.export',
    queue: [{ action: 'run', title: 'Подготовка', progressBarTitle: 'Строки' }],
});
process.showDialog();
// При окончательном удалении интерфейса:
// ProcessManager.delete('vendor-export');
```

- `showDialog()` показывает окно, кнопка запуска начинает запросы. Для controller вызывается BX.ajax.runAction(controller + '.' + action). Компонентный режим задаётся component и componentMode; не смешивай пространства имён.
- Сервер возвращает в data `STATUS: 'PROGRESS'` либо `'COMPLETED'`, числовые `PROCESSED_ITEMS`, `TOTAL_ITEMS`, при необходимости SUMMARY. PROGRESS повторяет текущий шаг, COMPLETED переходит к следующему. SUMMARY_HTML и часть SUMMARY вставляются как HTML: формируй безопасную строку.
- RequestStart получает FormData; StateChanged — состояние и результат. Обработчик StepCompleted вызывается на ответ шага, в том числе PROGRESS: не считай каждый вызов завершением очереди.
- cancel и finalize — отдельные действия, если нужны сценарию. PROCESS_TOKEN и состояние браузера не заменяют авторизацию, права и серверное хранение продолжения. Каждый запрос сохраняет стандартные фильтры Engine (`bitrix-controllers`).
- Это очередь браузера, не надёжный фоновый исполнитель. Если работа должна продолжиться после закрытия страницы, выполняй её через `bitrix-background-jobs`, а здесь показывай состояние.
