# Стадии, лента истории, текст BBCode

## `ui.stageflow`

`import { StageFlow } from 'ui.stageflow'` → StageFlow.Chart, StageFlow.Stage; глобальный BX.UI.StageFlow. Только JS.

```js
import { StageFlow } from 'ui.stageflow';

const chart = new StageFlow.Chart({
    backgroundColor: 'd3d7dc',    // 6 шестнадцатеричных цифр, без #
    currentStage: order.stageId,
    isActive: canEdit,            // по умолчанию false: только чтение
    onStageChange: (stage) => {
        api.setStage(order.id, stage.getId()).then(() => chart.setCurrentStageId(stage.getId()));
    },
}, [
    { id: 1, name: 'Новый', color: '2fc6f6' },
    { id: 2, name: 'Оплачен', color: '7bd500', isSuccess: true },
    { id: 3, name: 'Отменён', color: 'ff5752', isFail: true },
]);
container.append(chart.render()); // контейнер position: relative с явной высотой
```

- Диаграмма сама не переключается: сохрани на сервере, затем setCurrentStageId(id).
- Нужна хотя бы одна стадия isSuccess, у каждой id/name/color; иначе конструктор бросает исключение. Все успехи / неудачи свёрнуты в общую финальную стадию; для неудач окно запрашивает результат.
- Имена неуспешных стадий вставляются в окно без экранирования: экранируй при выводе.
- isDisable меняет только вид, onStageChange вызывается: проверяй права сервером.
- Добавления / удаления стадий нет: пересоздавай диаграмму. Одна на страницу — ID окон и финальные стадии общие.

## ui.timeline

`import { Timeline } from 'ui.timeline'` → Timeline.Stream, Item, History, StageChange, FieldsChange, Editor, CommentEditor; глобальный BX.UI.Timeline.

```js
import { Timeline } from 'ui.timeline';

const stream = new Timeline.Stream({ // объект обязателен: new Stream() бросает исключение
    items: [{
        id: 1, action: 'stage_change', userId: 5,
        createdTimestamp: 1727700000000, // миллисекунды
        title: 'Стадия изменена', // экранируется
        data: { stageFrom: { id: 1, name: 'Новый' }, stageTo: { id: 2, name: 'Оплачен' } },
    }],
    users: { 5: { fullName: 'Анна Иванова', link: '/company/personal/user/5/', photo: '' } },
    pageSize: 20,
});
container.append(stream.render());
stream.insertItem(stream.createItem(newItemData)); // с анимацией в начало
```

- description и htmlDescription комментария — **сырой HTML**. Собирай на сервере через `Converter::toHtml()` ниже / `CommentParser::getHtml()`, не из сырого ввода.
- action: item_create, stage_change, fields_change (`data.fields: [{ title }]`), comment; неизвестный → History.
- Свой тип: `class OrderPaid extends Timeline.History { renderMain() { … } }`, регистрация itemClasses: [['order_paid', OrderPaid]]. Встроенные коды не переопределяются.
- События BX.UI.Timeline.Stream: onScrollToTheBottom (`startProgress()`, загрузка, `addItem()` + `renderItems()`, `stopProgress()`), onPinClick (после переключения; сохрани item.isFixed).
- CommentEditor работает событиями: асинхронные onSave / onLoadVisualEditor (BX.UI.Timeline.CommentEditor). Сам запись не вставляет: вызови `createItem()` + insertItem().
- PHP: `Bitrix\UI\Timeline\Comment` (`getVisualEditorResponse()`, `saveFiles()`, `getFilesContentResponse()`), `Bitrix\UI\Timeline\CommentParser` (`getHtml()`, `getText()`, `getMentionedUserIds()`). `Comment::sendMentions()` зависит от rpa; вне RPA уведомляй своим кодом.

## BBCode и поля rich_text

**Since ui 26.600.** `Bitrix\UI\Format\BBCode\Converter` безопасно выводит BBCode: убирает запрещённые теги, вызывает CTextParser, затем CBXSanitizer.

```php
use Bitrix\UI\Format\BBCode\Converter;

$html = Converter::toHtml($bbCode); // безопасно для description истории и шаблонов
$text = Converter::toPlainText($bbCode); // индекс поиска, анонсы, темы писем
```

- Тип UF rich_text (`Bitrix\UI\UserField\Types\RichTextType`) хранит нормализованный BBCode (`Whitelist::normalize()`). Выводи только Converter, не регулярными выражениями.
- **Since ui 26.675** тегами считаются только [tag], [tag=v], [tag a=v], [/tag]; отдельные скобки хранятся как &#91; / &#93;. Пересчитай ранее сохранённый простой текст.
- До ui 26.600: `(new CTextParser())->convertText($bb)` + CBXSanitizer с разрешёнными тегами.

- **Since ui 26.700** rich_text кодирует эмодзи при записи и декодирует через onAfterFetch(). Прямой SQL обходит эту обработку: полученные так значения нужно явно декодировать `Bitrix\Main\Text\Emoji::decode()`. На старом ui сохраняй прежний формат, не декодируй без проверки хранилища.
