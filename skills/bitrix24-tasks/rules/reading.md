# Чтение задач

| Задача | API | Результат |
| --- | --- | --- |
| Одна задача | `V2\Public\Provider\TaskProvider::get(TaskParams)` | `?Entity\Task`; `null`, если отсутствует либо нет права чтения |
| Список/количество | `TaskProvider::getList(TaskListParams)`, `getCount()` | `TaskCollection` объектов `Entity\Task`, `int` |
| Совместимый список | `Bitrix\Tasks\Provider\TaskList::getList(Query\TaskQuery)` | массивы с ключами UPPER_CASE |

`TaskTable::getList()` не фильтрует права: для пользовательского списка не используй.

## Пример

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

## Правила и ограничения

- Фильтры: `[field, operator, value]`, формат `ConditionTree::createFromArray`. Операторы `in`, `like`, `=`, `!=`, `<`, `>=` …; группы — вложенные массивы, `'logic' => 'or'`. Имена — значения `FieldsEnum` camelCase: `responsibleId`, `createdBy`, `accomplice`, `auditor`, `member`, `groupId`, `realStatus`, `deadline`, `tag`, `parentId`, `flowId` …
- Неизвестные поля фильтра, выборки и сортировки молча удаляются. Опечатка может расширить результат. Используй `FieldsEnum`.
- `userId` задаёт фильтр прав. `skipAccessCheck: true` с `userId: 0` допустим только системному заданию, без пользовательского ввода.
- `TaskParams` по умолчанию загружает все части; отключай ненужные. `view: false` по умолчанию не отмечает просмотр. `get()` создаёт отсутствующий чат задачи.
- Фильтруй `realStatus` целыми `Status::*` (`Status::COMPLETED`, `getInWorkStatuses()`). `status` сущности — enum `Entity\Task\Status`. В legacy-строках `STATUS` вычисляемый метастатус; сравнивай `REAL_STATUS`.
- Даты — timestamp (`deadlineTs`, `createdTs`, `closedTs`). `DateTime::createFromTimestamp()` даёт серверное время; для вывода текущему пользователю применяй `->toUserTime()`.

## Совместимый список старой коробки

```php
$rows = (new \Bitrix\Tasks\Provider\TaskList())->getList(
    (new \Bitrix\Tasks\Provider\Query\TaskQuery($viewerId)) // фильтр прав пользователя $viewerId
        ->setSelect(['ID', 'TITLE', 'DEADLINE', 'REAL_STATUS'])
        ->setWhere(['=RESPONSIBLE_ID' => $responsibleId, '@REAL_STATUS' => [2, 3]])
        ->setOrder(['DEADLINE' => 'asc'])
        ->setLimit(50),
); // может бросить Bitrix\Tasks\Provider\Exception\TaskListException
```
