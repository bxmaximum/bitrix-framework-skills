# Timeline and activities

Operations write creation, change and stage records to the timeline themselves (`disableSaveToTimeline()` to skip). Add your own records as below.

## Comment

```php
use Bitrix\Crm\Timeline\CommentController;
use Bitrix\Crm\Timeline\CommentEntry;

$commentId = CommentEntry::create([
    'TEXT' => $text, // BB-code; empty → ArgumentException
    'AUTHOR_ID' => $userId, // default: current user
    'SETTINGS' => ['HAS_FILES' => 'N'],
    'BINDINGS' => [['ENTITY_TYPE_ID' => $entityTypeId, 'ENTITY_ID' => $entityId]],
]); // int, 0 on failure

if ($commentId > 0) {
    CommentController::getInstance()->onCreate($commentId, [
        'COMMENT' => $text,
        'ENTITY_TYPE_ID' => $entityTypeId,
        'ENTITY_ID' => $entityId,
    ]); // push to open cards
}
```

- No rights check inside: for user actions check `getUserPermissions()->item()->canUpdate($entityTypeId, $entityId)` first.
- Several `BINDINGS` show one comment in several cards.
- `CommentEntry::update($id, ['COMMENT' => $text])` and `CommentEntry::delete($id)` exist; they fire `OnAfterCrmTimelineComment{Update|Delete}`.

## To-do

```php
use Bitrix\Crm\Activity\Entity\ToDo;
use Bitrix\Crm\Activity\Provider\ToDo\ToDo as ToDoProvider;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Main\Type\DateTime;

$todo = (new ToDo(new ItemIdentifier($entityTypeId, $entityId), new ToDoProvider()))
    ->setSubject('Call back')
    ->setDescription('Discuss the offer')
    ->setDeadline((new DateTime())->add('+1 day'))
    ->setResponsibleId($userId)
    ->setCheckPermissions(false); // system job only; keep true for user actions

$result = $todo->save(); // Main\Result
$activityId = $todo->getId();
```

- Responsible defaults to the context user; `setCheckPermissions(true)` checks update rights on the owner.
- Loading: `(new ToDo($owner, new ToDoProvider()))->load($activityId)` returns `?ToDo`; `loadNearest()` gets the nearest open one.
- Saving an already completed to-do fails (`CAN_NOT_UPDATE_COMPLETED_TODO`).

## Other activities

```php
$open = \Bitrix\Crm\ActivityTable::getList([
    'select' => ['ID', 'SUBJECT', 'DEADLINE', 'PROVIDER_ID', 'RESPONSIBLE_ID'],
    'filter' => [
        '=BINDINGS.OWNER_TYPE_ID' => $entityTypeId,
        '=BINDINGS.OWNER_ID' => $entityId,
        '=COMPLETED' => 'N',
    ],
])->fetchAll();

\CCrmActivity::Complete($activityId, true); // no D7 replacement
```

- Read activities with `ActivityTable`/`ActivityBindingTable` (read-only). One activity can be bound to several owners.
- Writes go through providers (`Activity\Provider\*`) or `\CCrmActivity::Add/Update/Delete($id, …, $checkPerms, $regEvent, $options)`: compat API, still the kernel path for calls, e-mails and meetings. Don't insert into `ActivityTable`.
- Closing a deal/item in a final stage autocompletes open activities of completable providers (operation option `disableActivitiesAutocompletion()`).
