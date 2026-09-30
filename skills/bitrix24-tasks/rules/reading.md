# Reading tasks

| Need | API | Returns |
| --- | --- | --- |
| One task | `V2\Public\Provider\TaskProvider::get(TaskParams)` | `?Entity\Task`; `null` = missing **or** no read access |
| List, count | `TaskProvider::getList(TaskListParams)`, `getCount()` | `TaskCollection` of `Entity\Task`, `int` |
| Compat list | `Bitrix\Tasks\Provider\TaskList::getList(Query\TaskQuery)` | arrays with UPPER_CASE keys |

`TaskTable::getList()` has no access filter: never use it for user-facing lists.

## Example

```php
<?php

declare(strict_types=1);

namespace Vendor\Project\Integration\Tasks;

use Bitrix\Main\Loader;
use Bitrix\Main\Provider\Params\Pager;
use Bitrix\Main\Type\DateTime;
use Bitrix\Tasks\Internals\Task\Status;
use Bitrix\Tasks\V2\Public\Provider\Params\SortDirection;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\FieldsEnum;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListArrayFilter;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListParams;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListSelect;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskList\TaskListSort;
use Bitrix\Tasks\V2\Public\Provider\Params\TaskParams;
use Bitrix\Tasks\V2\Public\Provider\TaskProvider;

final class TaskReader
{
    public function __construct()
    {
        Loader::requireModule('tasks');
    }

    /** Overdue tasks of $responsibleId that $viewerId may see. */
    public function overdue(int $viewerId, int $responsibleId, int $limit = 50): array
    {
        $params = new TaskListParams(
            userId: $viewerId,
            pagination: new Pager($limit, 0),
            filter: new TaskListArrayFilter([
                [FieldsEnum::ResponsibleId->value, '=', $responsibleId],
                [FieldsEnum::RealStatus->value, 'in', Status::getInWorkStatuses()],
                [FieldsEnum::Deadline->value, '<', new DateTime()],
            ]),
            sort: new TaskListSort([FieldsEnum::Deadline->value => SortDirection::Asc->value]),
            select: new TaskListSelect([FieldsEnum::Id->value, FieldsEnum::Title->value, FieldsEnum::Deadline->value]),
        );

        $rows = [];
        foreach ((new TaskProvider())->getList($params) as $task) {
            $rows[] = ['id' => (int)$task->id, 'title' => (string)$task->title, 'deadlineTs' => $task->deadlineTs];
        }

        return $rows;
    }

    public function card(int $viewerId, int $taskId): ?array
    {
        $task = (new TaskProvider())->get(new TaskParams(
            taskId: $taskId,
            userId: $viewerId,
            flow: false,
            crm: false,
            email: false,
            gantt: false,
            placements: false,
        ));
        if ($task === null) {
            return null;
        }

        return [
            'title' => $task->title,
            'responsibleId' => $task->responsible?->id,
            'accompliceIds' => $task->accomplices?->getIds() ?? [],
            'auditorIds' => $task->auditors?->getIds() ?? [],
            'deadline' => $task->deadlineTs ? DateTime::createFromTimestamp($task->deadlineTs) : null,
            'chatId' => $task->chatId,
        ];
    }
}
```

## Rules and pitfalls

- Filter conditions are `[field, operator, value]` (`ConditionTree::createFromArray` format; `in`, `like`, `=`, `!=`, `<`, `>=` …; nest arrays for groups, `'logic' => 'or'`). Field names are `FieldsEnum` values (camelCase): `responsibleId`, `createdBy`, `accomplice`, `auditor`, `member`, `groupId`, `realStatus`, `deadline`, `tag`, `parentId`, `flowId` …
- Unknown filter, select or sort fields are dropped silently. A typo removes the condition and widens the result. Always use `FieldsEnum` cases.
- `userId` builds the access filter. `skipAccessCheck: true` (with `userId: 0`) only in system jobs, never with user input.
- `TaskParams` loads every part by default; switch off what you do not render. `view: false` (default) does not mark the task viewed. `get()` creates the task chat if it is missing.
- Status: filter by `realStatus` with `Status::*` ints (`Status::COMPLETED`, `getInWorkStatuses()`). Entity `status` is the `Entity\Task\Status` enum. In legacy rows `STATUS` is a computed meta-status; compare `REAL_STATUS`.
- Dates are Unix timestamps (`deadlineTs`, `createdTs`, `closedTs`). `DateTime::createFromTimestamp()` gives server time; `->toUserTime()` shifts to the current user for display.

## Compat list (older boxes)

```php
$rows = (new \Bitrix\Tasks\Provider\TaskList())->getList(
    (new \Bitrix\Tasks\Provider\Query\TaskQuery($viewerId)) // access filter for $viewerId
        ->setSelect(['ID', 'TITLE', 'DEADLINE', 'REAL_STATUS'])
        ->setWhere(['=RESPONSIBLE_ID' => $responsibleId, '@REAL_STATUS' => [2, 3]])
        ->setOrder(['DEADLINE' => 'asc'])
        ->setLimit(50),
); // throws Bitrix\Tasks\Provider\Exception\TaskListException
```
