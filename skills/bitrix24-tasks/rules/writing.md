# Запись задач

## Выбор API в tasks 26.x

| Уровень | Состояние в проверенной версии | Применение |
| --- | --- | --- |
| `Bitrix\Tasks\V2\Public\Command\Task\*Command` | публичный API; вызывается контроллерами ядра, `im`, `socialnetwork` | новый код |
| `Bitrix\Tasks\Control\Task` | `@deprecated`, делегирует V2-сервисам | совместимость |
| `CTaskItem`, `CTasks::Add/Update` | устаревшие обёртки `Control\Task` | совместимость/старое ядро |
| `Bitrix\Tasks\Internals\TaskTable` | ORM-таблица | только чтение и JOIN |

Пути записи приходят в V2 `AddTaskService`/`UpdateTaskService`/`DeleteTaskService`, поэтому основные события и побочные эффекты общие.

## Класс доступа к API

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
            return (new Result())->addError(new Error('Доступ запрещён', 'ACCESS_DENIED'));
        }

        return $this->run(new AddTaskCommand($task, new AddConfig(userId: $actorId)));
    }

    public function rename(int $actorId, int $taskId, string $title): Result
    {
        $patch = new Entity\Task(id: $taskId, title: $title); // свойства null остаются без изменения
        if (!(new TaskAccessService())->canSave($actorId, $patch)) {
            return (new Result())->addError(new Error('Доступ запрещён', 'ACCESS_DENIED'));
        }

        return $this->run(new UpdateTaskCommand($patch, new UpdateConfig(userId: $actorId)));
    }

    public function complete(int $actorId, int $taskId): Result
    {
        // Сначала проверяй ActionDictionary::ACTION_TASK_COMPLETE (rules/access-events.md).
        return $this->run(new CompleteTaskCommand($taskId, new UpdateConfig(userId: $actorId)));
    }

    public function delete(int $actorId, int $taskId): Result
    {
        if (!(new TaskAccessService())->canDelete($actorId, $taskId)) {
            return (new Result())->addError(new Error('Доступ запрещён', 'ACCESS_DENIED'));
        }

        return $this->run(new DeleteTaskCommand($taskId, new DeleteConfig(userId: $actorId)));
    }

    private function run(AbstractCommand $command): Result
    {
        try {
            return $command->run(); // V2 Result: getId(), getObject() возвращает Entity\Task
        } catch (CommandValidationException $e) { // пустой заголовок, нет постановщика/ответственного, id <= 0
            return (new Result())->addErrors($e->getValidationErrors());
        }
    }
}
```

## Правила

- `run()` возвращает `Bitrix\Tasks\V2\Internal\Result\Result`, наследник `Main\Result`. Исключения сервисов (`TaskAddException`, `TaskNotExistsException`, отмена обработчика) превращаются в ошибки; ошибки валидации бросают `CommandValidationException`.
- Изменение частичное: задавай только новые свойства. Коллекции `accomplices`, `auditors`, `tags` заменяются целиком; `deadlineTs: 0` очищает срок.
- Родитель: `parent: new Entity\Task(id: $parentId)`. Mapper добавления читает `parent`, не `parentId`.
- Статусы: `Status\CompleteTaskCommand`, `StartTaskCommand`, `PauseTaskCommand`, `DeferTaskCommand`, `RenewTaskCommand`, `ApproveTaskCommand`, `DisapproveTaskCommand`, аргументы `(int $taskId, UpdateConfig $config)`. При контроле (`needsControl`, постановщик отличается от ответственного) завершение другим пользователем даёт `SupposedlyCompleted`, затем постановщик принимает через `ApproveTaskCommand`.
- При установленном `recyclebin` удаление переносит задачу в корзину. `false` из `OnBeforeTaskDelete` возвращает ошибку.
- Флаги `AddConfig`/`UpdateConfig` (`skipNotifications`, `skipBP`, `needCorrectDatePlan`, …) — именованные аргументы. Передавай только необходимые после проверки текущей сигнатуры.

## Совместимость: старое ядро и массивы полей

```php
// При отказе/ошибке бросает \TasksException. DEADLINE передаётся объектом, не строкой.
$item = \CTaskItem::add(
    ['TITLE' => 'Позвонить клиенту', 'RESPONSIBLE_ID' => $responsibleId, 'DEADLINE' => new \Bitrix\Main\Type\DateTime()],
    $actorId,
);
\CTaskItem::getInstance($item->getId(), $actorId)->complete();
```
