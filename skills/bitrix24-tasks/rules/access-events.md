# Доступ, события и REST

## Доступ

`Bitrix\Tasks\Access\TaskAccessController::can($userId, string $action, $itemId = null, $params = null): bool`; действия — константы `Bitrix\Tasks\Access\ActionDictionary`. Модели кешируются в запросе: после изменения вызывай `TaskAccessController::dropItemCache($taskId)` до повторной проверки.

```php
$canRead = \Bitrix\Tasks\Access\TaskAccessController::can($userId, \Bitrix\Tasks\Access\ActionDictionary::ACTION_TASK_READ, $taskId);

// Создание из legacy-массива, как в CTaskItem::add.
$canCreate = \Bitrix\Tasks\Access\TaskAccessController::can(
    $userId,
    \Bitrix\Tasks\Access\ActionDictionary::ACTION_TASK_SAVE,
    null,
    \Bitrix\Tasks\Access\Model\TaskModel::createFromArray($fields),
);
```

| Операция | Проверка |
| --- | --- |
| Чтение | `ACTION_TASK_READ` |
| Создание/изменение V2-сущности | `(new TaskAccessService())->canSave($userId, $task)` (`ACTION_TASK_SAVE`, модели до/после) |
| Удаление | `(new TaskAccessService())->canDelete($userId, $taskId)` (`ACTION_TASK_REMOVE`) |
| Завершение | `ACTION_TASK_COMPLETE`, а при обязательном результате также `ACTION_TASK_COMPLETE_RESULT` |
| Начать, приостановить, отложить, возобновить, принять, отклонить, взять | `ACTION_TASK_START`, `_PAUSE`, `_DEFER`, `_RENEW`, `_APPROVE`, `_DISAPPROVE`, `_TAKE` |
| Срок | `ACTION_TASK_DEADLINE` |
| Участники | `rules/details.md` |
| Чек-лист | `ACTION_CHECKLIST_ADD`, `_EDIT`, `_TOGGLE`; фасад проверяет сам |
| Шаблон | `Bitrix\Tasks\Access\TemplateAccessController::can($userId, ActionDictionary::ACTION_TEMPLATE_READ, $templateId)`; `_SAVE`, `_REMOVE` |

## События модуля `tasks`

Отправляются V2-сервисами независимо от пути записи: команды, `Control\Task`, `CTaskItem`, REST. Механизм `ExecuteModuleEventEx` передаёт позиционные аргументы независимо от версии регистрации.

| Событие | Аргументы | Поведение |
| --- | --- | --- |
| `OnBeforeTaskAdd` | `array &$fields` | `false` отменяет; сообщение через `$APPLICATION->ThrowException()` |
| `OnTaskAdd` | `int $id, array &$fields, array $params` (`USER_ID`) | задача сохранена; исключения обработчика журналируются и подавляются |
| `OnBeforeTaskUpdate` | `int $id, array &$fields, array &$taskBefore` | `false` отменяет |
| `OnTaskUpdate` | `int $id, array &$fields, array &$taskData` | после сохранения; `$fields['META:PREV_FIELDS']` — прежняя строка. Исключение даёт ошибку вызывающему, хотя данные уже сохранены |
| `OnBeforeTaskDelete` | `int $id, array $taskData, array $byPass` | `false` останавливает удаление |
| `OnTaskDelete` | `int $id, array $params` (`FLOW_ID`, `USER_ID`) | после перемещения в корзину, до удаления строки |

Смена статуса (завершение, начало, делегирование …) проходит через изменение и отправляет `OnBeforeTaskUpdate`/`OnTaskUpdate`.

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
            // Поставь последующую работу в очередь (bitrix-background-jobs); не бросай исключение.
        }
    }
}
```

- Инициатор — `$params['USER_ID']`/`$fields['CHANGED_BY']`, не `$USER`.
- Обработчики должны быть короткими; тяжёлое — Messenger/агент. Регистрация при установке/удалении: `bitrix-events`.
- Сообщения чата: `im:OnAfterSendMessageExternalChatTasksTask`, `rules/details.md`.

## Соответствие REST и PHP

| REST приложения/облака | PHP в `/local` |
| --- | --- |
| `tasks.task.add` / `update` / `delete` | `AddTaskCommand` / `UpdateTaskCommand` / `DeleteTaskCommand` |
| `tasks.task.complete`, `start`, `defer`, `renew`, `approve` | `Status\*TaskCommand` |
| `tasks.task.delegate` | `Stakeholder\DelegateCommand` |
| `tasks.task.get` / `tasks.task.list` | `TaskProvider::get()` / `getList()` |
| `task.checklistitem.add` / `complete` | `TaskCheckListFacade::add()` / `complete()` |
| `tasks.task.chat.message.send` | `Chat\SendMessageCommand` |
| `task.commentitem.add` старой карточки | `CTaskCommentItem::add()` |
| `OnTaskAdd`/`OnTaskUpdate`/`OnTaskDelete` | те же события модуля `tasks` |


**Since tasks 26.800** V2-путь `OnTaskAdd` добавляет в `$params` признаки `FROM_AGENT`, `FROM_WORKFLOW`, `MANUAL_DEADLINE`. Они позволяют различить источник и вручную заданный срок. На старой версии считай отсутствие признака неизвестным, не доказательством ручного создания.

В этой же версии вложенное изменение `STAGE_ID` при синхронизации Scrum не отправляет отдельные `OnBeforeTaskUpdate`/`OnTaskUpdate` и не запускает БП: стадия входит во внешнее изменение. Обработчик не должен ждать второго события; в старом ядре защищайся от повторных уведомлений.
