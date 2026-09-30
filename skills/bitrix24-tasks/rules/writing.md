# Writing tasks

## Which API (tasks 26.x)

| Layer | State in tasks 26.300.100 | Use |
| --- | --- | --- |
| `Bitrix\Tasks\V2\Public\Command\Task\*Command` | public API; kernel controllers, `im`, `socialnetwork` call it | new code |
| `Bitrix\Tasks\Control\Task` | `@deprecated`, delegates to V2 services | compat |
| `CTaskItem`, `CTasks::Add/Update` | `@deprecated` wrappers over `Control\Task` | compat, older boxes |
| `Bitrix\Tasks\Internals\TaskTable` | ORM table | read joins only |

All paths end in the V2 `AddTaskService`/`UpdateTaskService`/`DeleteTaskService`, so events and side effects are identical.

## Gateway

```php
<?php

declare(strict_types=1);

namespace Vendor\Project\Integration\Tasks;

use Bitrix\Main\Command\Exception\CommandValidationException;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use Bitrix\Tasks\V2\Internal\Entity;
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Add\Config\AddConfig;
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Delete\Config\DeleteConfig;
use Bitrix\Tasks\V2\Internal\Service\Task\Action\Update\Config\UpdateConfig;
use Bitrix\Tasks\V2\Public\Command\AbstractCommand;
use Bitrix\Tasks\V2\Public\Command\Task\AddTaskCommand;
use Bitrix\Tasks\V2\Public\Command\Task\DeleteTaskCommand;
use Bitrix\Tasks\V2\Public\Command\Task\Status\CompleteTaskCommand;
use Bitrix\Tasks\V2\Public\Command\Task\UpdateTaskCommand;
use Bitrix\Tasks\V2\Public\Service\Access\TaskAccessService;

final class TaskGateway
{
    public function __construct()
    {
        Loader::requireModule('tasks');
    }

    public function create(int $actorId, string $title, int $responsibleId, ?int $deadlineTs, int $groupId = 0): Result
    {
        $task = new Entity\Task(
            title: $title,
            creator: new Entity\User(id: $actorId),
            responsible: new Entity\User(id: $responsibleId),
            deadlineTs: $deadlineTs,
            group: $groupId > 0 ? new Entity\Group(id: $groupId) : null,
            auditors: Entity\UserCollection::mapFromIds([$actorId]),
            priority: Entity\Priority::High,
        );
        if (!(new TaskAccessService())->canSave($actorId, $task)) {
            return (new Result())->addError(new Error('Access denied', 'ACCESS_DENIED'));
        }

        return $this->run(new AddTaskCommand($task, new AddConfig(userId: $actorId)));
    }

    public function rename(int $actorId, int $taskId, string $title): Result
    {
        $patch = new Entity\Task(id: $taskId, title: $title); // null properties stay unchanged
        if (!(new TaskAccessService())->canSave($actorId, $patch)) {
            return (new Result())->addError(new Error('Access denied', 'ACCESS_DENIED'));
        }

        return $this->run(new UpdateTaskCommand($patch, new UpdateConfig(userId: $actorId)));
    }

    public function complete(int $actorId, int $taskId): Result
    {
        // check ActionDictionary::ACTION_TASK_COMPLETE first (rules/access-events.md)
        return $this->run(new CompleteTaskCommand($taskId, new UpdateConfig(userId: $actorId)));
    }

    public function delete(int $actorId, int $taskId): Result
    {
        if (!(new TaskAccessService())->canDelete($actorId, $taskId)) {
            return (new Result())->addError(new Error('Access denied', 'ACCESS_DENIED'));
        }

        return $this->run(new DeleteTaskCommand($taskId, new DeleteConfig(userId: $actorId)));
    }

    private function run(AbstractCommand $command): Result
    {
        try {
            return $command->run(); // V2 Result: getId(), getObject() -> Entity\Task
        } catch (CommandValidationException $e) { // empty title, no creator/responsible, id <= 0
            return (new Result())->addErrors($e->getValidationErrors());
        }
    }
}
```

## Rules

- `run()` returns `Bitrix\Tasks\V2\Internal\Result\Result` (extends `Main\Result`). Service exceptions (`TaskAddException`, `TaskNotExistsException`, handler cancellations) become errors; validation failures throw `CommandValidationException`.
- Update is a patch: set only changed properties. Collections (`accomplices`, `auditors`, `tags`) replace the whole set; `deadlineTs: 0` clears the deadline.
- Parent task: `parent: new Entity\Task(id: $parentId)` (the add mapper reads `parent`, not `parentId`).
- Status: `Status\CompleteTaskCommand`, `StartTaskCommand`, `PauseTaskCommand`, `DeferTaskCommand`, `RenewTaskCommand`, `ApproveTaskCommand`, `DisapproveTaskCommand` — each `(int $taskId, UpdateConfig $config)`. With control on (`needsControl`, creator ≠ responsible) complete by anyone but the creator gives `SupposedlyCompleted`; the creator then approves (`ApproveTaskCommand`).
- Delete moves the task to the recycle bin when `recyclebin` is installed; `OnBeforeTaskDelete` returning `false` yields an error.
- Config flags (`AddConfig`, `UpdateConfig`: `skipNotifications`, `skipBP`, `needCorrectDatePlan`, …) are named arguments; pass only what you need.

## Compat (older boxes, array fields)

```php
// throws \TasksException on denial or error; DEADLINE as object, not string
$item = \CTaskItem::add(
    ['TITLE' => 'Call client', 'RESPONSIBLE_ID' => $responsibleId, 'DEADLINE' => new \Bitrix\Main\Type\DateTime()],
    $actorId,
);
\CTaskItem::getInstance($item->getId(), $actorId)->complete();
```
