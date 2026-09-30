# Operations: add, update, delete

`launch()` order: limits → rights of the context user → running-workflow check → field processing (defaults, stage fix-up, sums) → required fields → before-save actions → save → rights attributes, search, duplicates, counters → history, timeline → after-save actions (legacy events, live feed) → pull → business processes → robots.

## Add, update, delete

```php
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Context;

$factory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
$context = (new Context())->setUserId($userId); // rights check, CREATED_BY/UPDATED_BY

$item = $factory->createItem([
    Item::FIELD_NAME_TITLE => 'Order #15',
    Item::FIELD_NAME_CATEGORY_ID => $categoryId,
    Item::FIELD_NAME_ASSIGNED => $userId,
]);
$result = $factory->getAddOperation($item, $context)->launch();
if (!$result->isSuccess()) {
    return $result; // rights, required fields, handler cancel
}
$id = $item->getId();

$item = $factory->getItem($id);
$item->setStageId($stageId);
$result = $factory->getUpdateOperation($item, $context)->launch();

// DANGER: moves to recycle bin if enabled for the type, otherwise deletes for good
$result = $factory->getDeleteOperation($item, $context)->launch();
```

- Without `$context` the shared `Container::getInstance()->getContext()` is used: current `$USER`, user 0 in agents/CLI/queues → `CRM_ITEM_ADD_ACCESS_DENIED`. Don't mutate the shared context; create your own.
- `setScope(Context::SCOPE_TASK)` (also `SCOPE_AUTOMATION`, `SCOPE_REST`, `SCOPE_MANUAL`) marks the origin.
- `createItem()` keys are common field names; default UF values are applied.
- Invalid `STAGE_ID` on update is silently reverted to the old stage. On category change an invalid stage becomes the first stage of the new category.
- Category change fails with `CRM_ITEM_HAS_RUNNING_WORKFLOWS` while business processes run.
- Update with no changes returns success and saves nothing (no actions, no events).
- After-save errors land in the `Result`, but the item is already saved.

## Switching checks off

Call on the operation before `launch()`; each has `enable*()`/`is*()` twins.

| Method | Skips |
| --- | --- |
| `disableCheckAccess()` | rights of the context user |
| `disableCheckFields()` | required fields, incl. stage-dependent |
| `disableCheckRequiredUserFields()` | mandatory UF |
| `disableCheckLimits()` | tariff restrictions |
| `disableCheckWorkflows()` | running-BP check on category change |
| `disableAllChecks()` | the five above |
| `disableAutomation()` / `disableBizProc()` | robots / BP autostart |
| `disableSaveToTimeline()` / `disableSaveToHistory()` | timeline / event history |
| `disableFieldProcession()` | defaults, stage fix-up, calculated fields: avoid |
| `disableBeforeSaveActions()` / `disableAfterSaveActions()` | all actions incl. legacy `OnBefore*`/`OnAfter*` events and cache cleanup: avoid |

Mass import: `disableAllChecks()`, `disableAutomation()`, `disableBizProc()` on each operation; keep events and timeline unless profiling says otherwise.

## Custom actions

```php
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Operation;
use Bitrix\Main\Error;
use Bitrix\Main\Result;

final class RequireComment extends Operation\Action
{
    public function process(Item $item): Result
    {
        $result = new Result();
        if ($item->isChangedStageId() && trim((string)$item->getComments()) === '') {
            $result->addError(new Error('Fill in the comment', 'VENDOR_COMMENT_REQUIRED'));
        }

        return $result;
    }
}

$operation = $factory->getUpdateOperation($item, $context);
$operation->addAction(Operation::ACTION_BEFORE_SAVE, new RequireComment());
$result = $operation->launch();
```

- `ACTION_BEFORE_SAVE` runs only if the item changed; `isChanged()`/`remindActual()` give the diff; an error cancels the save.
- `ACTION_AFTER_SAVE`: `$this->getItemBeforeSave()` is the previous state, `$this->getContext()` the context; an error is reported, nothing is rolled back.
- Third argument `$sort` (default 500) orders actions; `removeAction($placement, $className)` drops a kernel action.
- `addAction()` affects only this operation object. To hook UI and REST writes, override the factory (`rules/events-automation.md`).
