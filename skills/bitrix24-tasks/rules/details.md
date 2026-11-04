# Участники, сроки, чек-листы, шаблоны и комментарии

Команды: `Bitrix\Tasks\V2\Public\Command\Task`, конфигурации: `Bitrix\Tasks\V2\Internal\Service\Task\Action\{Add,Update,Delete}\Config`. Вызывай как в `rules/writing.md`; для перечисленных операций проверяй права отдельно.

## Участники

| Изменение | Команда | Право |
| --- | --- | --- |
| Заменить соисполнителей | `Stakeholder\SetAccomplicesCommand(taskId, accompliceIds, UpdateConfig)` | `ACTION_TASK_CHANGE_ACCOMPLICES` |
| Добавить наблюдателей | `Stakeholder\AddAuditorsCommand(taskId, auditorIds, UpdateConfig)` | `ACTION_TASK_ADD_AUDITORS` |
| Заменить/удалить наблюдателей | `Stakeholder\SetAuditorsCommand`, `DeleteAuditorsCommand`, аргументы как выше | `ACTION_TASK_SAVE` |
| Наблюдать/прекратить | `Audit\WatchTaskCommand(taskId, userId, auditorId)`, `Audit\UnwatchTaskCommand(…)` | `ACTION_TASK_READ` |
| Сменить ответственного | `Stakeholder\DelegateCommand(taskId, responsibleId, UpdateConfig)` | `ACTION_TASK_DELEGATE` либо `ACTION_TASK_CHANGE_RESPONSIBLE` |
| Сменить постановщика | `Stakeholder\UpdateCreatorCommand(taskId, creatorId, responsibleId, UpdateConfig)` | `ACTION_TASK_CHANGE_DIRECTOR` |

- Ядро проверяет действия по моделям до/после изменения. Общая проверка: `canSave($userId, new Entity\Task(id: $taskId, accomplices: …))` сервиса `TaskAccessService`.
- Делегирование возвращает статус ожидания и добавляет прежнего ответственного в наблюдатели, если он ещё не участник и проект не Scrum.
- Коллекции `accomplices`/`auditors` в `UpdateTaskCommand` целиком заменяют состав.

## Сроки и часовые пояса

- Для пользовательской смены срока: `Deadline\UpdateDeadlineCommand(taskId, deadlineTs, updateConfig, reason)`. Применяет `DeadlinePolicy`: число переносов, обязательную причину, например в потоках. Нарушение даёт ошибку. `deadlineTs` в обычном `UpdateTaskCommand` обходит эту политику; оставь для системного изменения.
- `ACTION_TASK_DEADLINE` учитывает `allowsChangeDeadline` (`ALLOW_CHANGE_DEADLINE`).
- Legacy-строка `DEADLINE` разбирается через `DateTime::createFromUserTime()` со смещением пользователя сессии, не переданного инициатора. В агенте/CLI используется серверное время. Передавай `Main\Type\DateTime` либо timestamp; `CTasks::Add()` также принимает `['SKIP_TIMEZONE' => ['DEADLINE']]`.

## Чек-листы

`Bitrix\Tasks\CheckList\Task\TaskCheckListFacade` сам проверяет `ACTION_CHECKLIST_ADD`/`_EDIT`/`_TOGGLE`. Результат `Bitrix\Tasks\Util\Result`: `isSuccess()`, `getErrors()->getMessages()`, `getData()['ITEM']`.

```php
$root = \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::add($taskId, $userId, ['TITLE' => 'Релиз', 'PARENT_ID' => 0]);
if ($root->isSuccess()) {
    $rootId = (int)$root->getData()['ITEM']->getFields()['ID'];
    \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::add($taskId, $userId, ['TITLE' => 'Отметить сборку', 'PARENT_ID' => $rootId]);
}

// Завершить один пункт, как в REST.
$fields = \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::getList([], ['ID' => $itemId])[$itemId] ?? null;
if ($fields !== null) {
    $item = new \Bitrix\Tasks\CheckList\Internals\CheckList(0, $userId, \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::class, $fields);
    \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::complete($taskId, $userId, $item);
}
```

- `PARENT_ID => 0` создаёт корень чек-листа; пункты ссылаются на его ID. Ключи: `TITLE`, `PARENT_ID`, `SORT_INDEX`, `IS_COMPLETE`, `IS_IMPORTANT`, `MEMBERS`, `ATTACHMENTS`.
- `getList($select, ['TASK_ID' => $taskId])` не проверяет права: сначала `ACTION_TASK_READ`. Ключи результата — ID пунктов.
- Массово, без проверки прав: `V2\Public\Command\CheckList\CompleteCheckListItemsCommand(ids, userId)` / `RenewCheckListItemsCommand`.

## Теги и проекты

- Теги: `tags: Entity\TagCollection::mapFromArray([['name' => 'urgent']])` заменяет набор; legacy `TAGS => ['urgent']`. Для чтения: `TaskParams(tags: true)`, `$task->tags`.
- Проект: `group: new Entity\Group(id: $groupId)`/`GROUP_ID`. Создание требует права инструмента socialnetwork `tasks/create_tasks`, проверяется через `ACTION_TASK_SAVE`. Для списка: `FieldsEnum::GroupId`.

## Создание из шаблона

```php
$locator = \Bitrix\Main\DI\ServiceLocator::getInstance();
$task = $locator->get(\Bitrix\Tasks\V2\Public\Provider\Template\Task\TemplateToTaskProvider::class)
    ->get(new \Bitrix\Tasks\V2\Public\Provider\Params\Template\Task\TemplateToTaskParams(userId: $userId, templateId: $templateId));
if ($task !== null) { // null: шаблона нет либо нет права чтения
    $result = (new \Bitrix\Tasks\V2\Public\Command\Template\Task\AddTaskCommand(
        templateId: $templateId,
        taskData: $task->cloneWith(['deadlineTs' => $deadlineTs]),
        config: new \Bitrix\Tasks\V2\Internal\Service\Template\Task\Add\Config\AddTaskConfig(userId: $userId, withSubTasks: true),
    ))->run();
}
```

`taskData` — полная новая задача, предварительно заполненная провайдером из шаблона. Команда сама проверяет `canSave`.

## Комментарии

- Новая карточка (tasks 25.700+ по REST-документации): комментарий — сообщение чата. От пользователя отправляй `Chat\SendMessageCommand(taskId, userId, new \Bitrix\Tasks\V2\Internal\Integration\Im\Entity\Message(text: $text))` после проверки `ACTION_TASK_READ`. ID чата: `(new \Bitrix\Tasks\V2\Public\Provider\ChatProvider())->getByTaskId($taskId)?->getId()`, далее API `bitrix24-im` с диалогом `chat<ID>`.
- Реакция: `im:OnAfterSendMessageExternalChatTasksTask`, событие `AfterSendMessageEvent`, `getChat()->getEntityId()` — ID задачи, `getMessage()` — сообщение; выполняется после ответа.
- `tasks:OnAfterCommentAdd` сохраняется для комментариев форума; для сообщений чата его получают только REST-обработчики.
- Старая карточка/форум: `\CTaskCommentItem::add(\CTaskItem::getInstance($taskId, $userId), ['POST_MESSAGE' => $text])`.

Изменения контрактов БП и REST: `rules/integrations.md`.
