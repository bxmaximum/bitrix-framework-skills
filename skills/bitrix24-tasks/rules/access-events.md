# Access, events, REST mapping

## Access

`Bitrix\Tasks\Access\TaskAccessController::can($userId, string $action, $itemId = null, $params = null): bool` with `Bitrix\Tasks\Access\ActionDictionary` constants. Task models are cached per hit: after changing a task, call `TaskAccessController::dropItemCache($taskId)` before checking again in the same hit.

```php
$canRead = \Bitrix\Tasks\Access\TaskAccessController::can($userId, \Bitrix\Tasks\Access\ActionDictionary::ACTION_TASK_READ, $taskId);

// create from legacy array fields (what CTaskItem::add does)
$canCreate = \Bitrix\Tasks\Access\TaskAccessController::can(
    $userId,
    \Bitrix\Tasks\Access\ActionDictionary::ACTION_TASK_SAVE,
    null,
    \Bitrix\Tasks\Access\Model\TaskModel::createFromArray($fields),
);
```

| Operation | Check |
| --- | --- |
| Read | `ACTION_TASK_READ` |
| Create or patch with V2 entity | `(new TaskAccessService())->canSave($userId, $task)` (`ACTION_TASK_SAVE`, before/after models) |
| Delete | `(new TaskAccessService())->canDelete($userId, $taskId)` (`ACTION_TASK_REMOVE`) |
| Complete | `ACTION_TASK_COMPLETE` and `ACTION_TASK_COMPLETE_RESULT` (result required) |
| Start, pause, defer, renew, approve, disapprove, take | `ACTION_TASK_START`, `_PAUSE`, `_DEFER`, `_RENEW`, `_APPROVE`, `_DISAPPROVE`, `_TAKE` |
| Deadline | `ACTION_TASK_DEADLINE` |
| Members | see `rules/details.md` |
| Checklist | `ACTION_CHECKLIST_ADD`, `_EDIT`, `_TOGGLE` (facade checks itself) |
| Template | `Bitrix\Tasks\Access\TemplateAccessController::can($userId, ActionDictionary::ACTION_TEMPLATE_READ, $templateId)`; `_SAVE`, `_REMOVE` |

## Events (module `tasks`)

Fired from the V2 services for every write path (commands, `Control\Task`, `CTaskItem`, REST). Dispatch is `ExecuteModuleEventEx`: the handler gets positional arguments whatever the registration version.

| Event | Handler arguments | Notes |
| --- | --- | --- |
| `OnBeforeTaskAdd` | `array &$fields` | return `false` to cancel; the message comes from `$APPLICATION->ThrowException()` |
| `OnTaskAdd` | `int $id, array &$fields, array $params` (`USER_ID`) | task saved; handler exceptions are logged and swallowed |
| `OnBeforeTaskUpdate` | `int $id, array &$fields, array &$taskBefore` | `false` cancels |
| `OnTaskUpdate` | `int $id, array &$fields, array &$taskData` | after save; `$fields['META:PREV_FIELDS']` = previous row; an exception makes the caller get an error although data is saved |
| `OnBeforeTaskDelete` | `int $id, array $taskData, array $byPass` | `false` stops deletion |
| `OnTaskDelete` | `int $id, array $params` (`FLOW_ID`, `USER_ID`) | after recycle-bin move, before the row is deleted |

Status changes (complete, start, delegate …) go through update and fire `OnBeforeTaskUpdate`/`OnTaskUpdate`.

```php
<?php

declare(strict_types=1);

namespace Vendor\Project\Integration\Tasks;

use Bitrix\Tasks\Internals\Task\Status;

final class TaskEventHandler
{
    // EventManager::getInstance()->addEventHandler('tasks', 'OnTaskUpdate', [self::class, 'onTaskUpdate']);
    public static function onTaskUpdate(int $taskId, array &$fields, array &$taskData): void
    {
        $was = (int)($fields['META:PREV_FIELDS']['REAL_STATUS'] ?? 0);
        $now = (int)($fields['STATUS'] ?? $was);
        if ($now === Status::COMPLETED && $was !== Status::COMPLETED) {
            // enqueue follow-up work (bitrix-background-jobs); never throw here
        }
    }
}
```

- The actor is `$params['USER_ID']` / `$fields['CHANGED_BY']`, not `$USER`.
- Keep handlers fast; heavy work goes to Messenger or an agent. Registration on install/uninstall: `bitrix-events`.
- Task chat messages: `im:OnAfterSendMessageExternalChatTasksTask`, see `rules/details.md`.

## REST ↔ PHP

| REST (apps, cloud) | PHP in `/local` |
| --- | --- |
| `tasks.task.add` / `update` / `delete` | `AddTaskCommand` / `UpdateTaskCommand` / `DeleteTaskCommand` |
| `tasks.task.complete`, `start`, `defer`, `renew`, `approve` | `Status\*TaskCommand` |
| `tasks.task.delegate` | `Stakeholder\DelegateCommand` |
| `tasks.task.get` / `tasks.task.list` | `TaskProvider::get()` / `getList()` |
| `task.checklistitem.add` / `complete` | `TaskCheckListFacade::add()` / `complete()` |
| `tasks.task.chat.message.send` | `Chat\SendMessageCommand` |
| `task.commentitem.add` (old card) | `CTaskCommentItem::add()` |
| `OnTaskAdd` / `OnTaskUpdate` / `OnTaskDelete` | same `tasks` module events |
