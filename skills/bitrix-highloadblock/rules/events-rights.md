# Events and rights

## Double dispatch

Every record event is sent **twice**, module `''` (global-namespace entity), names built from block `NAME`:

1. legacy `<Name><Event>`, e.g. `ProductColorOnBeforeAdd`. Reduced payload: update events have no `primary`/`oldFields`, delete events only `id`.
2. modern `\<Name>::<Event>`, e.g. `\ProductColor::OnBeforeAdd`. Full payload; `Bitrix\Main\ORM\EventManager` subscribes here.

Subscribe to one form only: both → the handler runs twice and errors from both land in the `Result`. Renaming `NAME` silently orphans all handlers.

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\EntityError;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventManager;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\ORM\Fields\FieldError;

// needs the compiled data class; returns a key for removeEventHandler()
$key = EventManager::getInstance()->addEventHandler(
    $dataClass,
    DataManager::EVENT_ON_BEFORE_ADD,
    static function (Event $event): EventResult {
        $result = new EventResult();
        $code = trim((string)($event->getParameter('fields')['UF_CODE'] ?? ''));
        if ($code === '') {
            $result->addError(new FieldError($event->getEntity()->getField('UF_CODE'), 'Code required'));
            return $result;
        }
        $result->modifyFields(['UF_CODE' => mb_strtolower($code)]);
        return $result;
    }
);
```

- Also `removeEventHandler($dataClass, $type, $key)`, persistent `registerEventHandler($dataClass, $type, $toModuleId, $toClass, $toMethod)` / `unRegisterEventHandler(...)`. All resolve the entity (block must compile); unknown type → `ArgumentException`.
- Without compiling on every hit, subscribe by name: `\Bitrix\Main\EventManager::getInstance()->addEventHandler('', '\ProductColor::OnAfterAdd', [Handler::class, 'onAfterAdd'])` in `init.php`, or `registerEventHandler('', '\ProductColor::OnAfterAdd', 'vendor.module', Handler::class, 'onAfterAdd')` on module install (`bitrix-events`).

## Parameters (modern form)

| Event | Params | Cancel |
| --- | --- | --- |
| `OnBeforeAdd` | `fields`, `object` (mutable) | `addError()`; `modifyFields()`, `unsetFields()` |
| `OnAdd` / `OnAfterAdd` | `fields`, `object` (clone); after: + `id`, `primary` | no |
| `OnBeforeUpdate` | `id`, `primary`, `fields` (only passed keys), `object`, `oldFields` | as `OnBeforeAdd` |
| `OnUpdate` / `OnAfterUpdate` | same | no |
| `OnBeforeDelete` / `OnDelete` / `OnAfterDelete` | `id`, `primary`, `oldFields` | before only |

- `OnBefore*` run before UF validation. `OnAdd`/`OnUpdate`/`OnDelete` fire just before SQL. After an `OnBeforeDelete` error the later delete events do not fire.
- `oldFields` holds scalar values before the change; multiple fields are unreliable there — read them before `update()`. It does not lock or version the row.
- `FieldError` for one field, `EntityError` for record rules. `unsetFields()` drops silently (use `addError` if the caller must know). Several handlers changing one field → order-dependent.
- Calling the same data class inside its handler re-fires events: guard with `static $running` + `try/finally` (per process only). After-events are not transactional with your side effects.

## Rights

Module tasks: `hblock_denied` (D, none), `hblock_read` (R → `hl_element_read`), `hblock_write` (W → `hl_element_write`, `hl_element_delete`). Per block, not per record. **`hblock_write` does not include `hl_element_read`**. The kernel admin and components treat any operation as read access.

```php
use Bitrix\Highloadblock\HighloadBlockRightsTable;
use Bitrix\Main\TaskTable;

$ops = HighloadBlockRightsTable::getOperationsName([$hlId])[$hlId] ?? []; // scalar form warns when empty
$canRead = $USER->IsAdmin() || array_intersect(['hl_element_read', 'hl_element_write'], $ops) !== [];
$canWrite = $USER->IsAdmin() || in_array('hl_element_write', $ops, true);

// grant in a migration; never hardcode TASK_ID
$taskId = (int)TaskTable::getList([
    'select' => ['ID'], 'filter' => ['=MODULE_ID' => 'highloadblock', '=NAME' => 'hblock_read'],
])->fetch()['ID'];
HighloadBlockRightsTable::add(['HL_ID' => $hlId, 'TASK_ID' => $taskId, 'ACCESS_CODE' => 'G2']);
```

`getOperationsName()` reads global `$USER` → fatal in CLI/agents without it. There, decide the access model explicitly (never default to admin).
