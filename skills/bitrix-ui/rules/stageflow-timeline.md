# Stage bar, history feed, BBCode text

## `ui.stageflow`

`import { StageFlow } from 'ui.stageflow'` → `StageFlow.Chart`, `StageFlow.Stage` (global `BX.UI.StageFlow`). JS only.

```js
import { StageFlow } from 'ui.stageflow';

const chart = new StageFlow.Chart({
    backgroundColor: 'd3d7dc',    // 6 hex digits, no "#"
    currentStage: order.stageId,
    isActive: canEdit,            // default false = read-only
    onStageChange: (stage) => {
        api.setStage(order.id, stage.getId()).then(() => chart.setCurrentStageId(stage.getId()));
    },
}, [
    { id: 1, name: 'New', color: '2fc6f6' },
    { id: 2, name: 'Paid', color: '7bd500', isSuccess: true },
    { id: 3, name: 'Cancelled', color: 'ff5752', isFail: true },
]);
container.append(chart.render()); // container: position: relative + explicit height
```

- The chart does not move itself: save on the server, then `setCurrentStageId(id)`.
- At least one `isSuccess` stage and `id`/`name`/`color` on every stage are required, or construction throws. All success/fail stages collapse into one final stage; with fail stages a popup asks for the result.
- Fail-stage names go into that popup unescaped: escape names on output.
- `isDisable` is visual only: `onStageChange` still fires; check rights on the server.
- No add/remove API: recreate the chart. One chart per page (popup ids and final-stage data are shared).

## `ui.timeline`

`import { Timeline } from 'ui.timeline'` → `Timeline.Stream`, `Item`, `History`, `StageChange`, `FieldsChange`, `Editor`, `CommentEditor` (global `BX.UI.Timeline`).

```js
import { Timeline } from 'ui.timeline';

const stream = new Timeline.Stream({       // pass an object: new Stream() throws
    items: [{
        id: 1, action: 'stage_change', userId: 5,
        createdTimestamp: 1727700000000,   // milliseconds
        title: 'Stage changed',            // escaped
        data: { stageFrom: { id: 1, name: 'New' }, stageTo: { id: 2, name: 'Paid' } },
    }],
    users: { 5: { fullName: 'Anna Smith', link: '/company/personal/user/5/', photo: '' } },
    pageSize: 20,
});
container.append(stream.render());
stream.insertItem(stream.createItem(newItemData)); // animated, at the top
```

- `description` and comment `htmlDescription` are inserted as **raw HTML**: build them server side (`Converter::toHtml()` below, `CommentParser::getHtml()`), never from raw input.
- `action`: `item_create`, `stage_change`, `fields_change` (`data.fields: [{ title }]`), `comment`; unknown → `History`.
- Custom item type: `class OrderPaid extends Timeline.History { renderMain() { … } }`, registered via `itemClasses: [['order_paid', OrderPaid]]` (cannot override built-in codes).
- Events (namespace `BX.UI.Timeline.Stream`): `onScrollToTheBottom` (call `startProgress()`, load, `addItem()` + `renderItems()`, `stopProgress()`), `onPinClick` (after toggle; persist `item.isFixed`).
- `CommentEditor` is event-driven: handle async `onSave` / `onLoadVisualEditor` (`BX.UI.Timeline.CommentEditor`); it does not insert the item: `createItem()` + `insertItem()` yourself.
- PHP helpers: `Bitrix\UI\Timeline\Comment` (`getVisualEditorResponse()`, `saveFiles()`, `getFilesContentResponse()`), `Bitrix\UI\Timeline\CommentParser` (`getHtml()`, `getText()`, `getMentionedUserIds()`). `Comment::sendMentions()` depends on the `rpa` module: send notifications yourself outside RPA.

## BBCode and `rich_text` fields

**Since ui 26.600.** `Bitrix\UI\Format\BBCode\Converter` renders BBCode safely: forbidden tags stripped, `CTextParser`, then `CBXSanitizer`.

```php
use Bitrix\UI\Format\BBCode\Converter;

$html = Converter::toHtml($bbCode);        // safe for timeline description or templates
$text = Converter::toPlainText($bbCode);   // search index, previews, e-mail subjects
```

- User field type `rich_text` (`Bitrix\UI\UserField\Types\RichTextType`) stores normalized BBCode (`Whitelist::normalize()`); render its value only through `Converter`, never by regex.
- Since ui 26.675 only `[tag]`, `[tag=v]`, `[tag a=v]`, `[/tag]` count as tags; stray brackets are stored as `&#91;` / `&#93;`. Recompute stored plain text built before that.
- Fallback before 26.600: `(new CTextParser())->convertText($bb)` + `CBXSanitizer` with a tag whitelist.
