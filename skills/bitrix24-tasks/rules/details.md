# Members, deadlines, checklists, tags, projects, templates, comments

Commands below live in namespace Bitrix\Tasks\V2\Public\Command\Task, configs in Bitrix\Tasks\V2\Internal\Service\Task\Action\{Add,Update,Delete}\Config. Run them like in `rules/writing.md`; none checks rights.

## Members

| Change | Command | Access action |
| --- | --- | --- |
| Replace accomplices | `Stakeholder\SetAccomplicesCommand(taskId, accompliceIds, UpdateConfig)` | `ACTION_TASK_CHANGE_ACCOMPLICES` |
| Add auditors | `Stakeholder\AddAuditorsCommand(taskId, auditorIds, UpdateConfig)` | `ACTION_TASK_ADD_AUDITORS` |
| Replace / remove auditors | `Stakeholder\SetAuditorsCommand`, `DeleteAuditorsCommand` (same args) | `ACTION_TASK_SAVE` |
| Watch / stop watching | `Audit\WatchTaskCommand(taskId, userId, auditorId)`, `Audit\UnwatchTaskCommand(…)` | `ACTION_TASK_READ` |
| New responsible | `Stakeholder\DelegateCommand(taskId, responsibleId, UpdateConfig)` | `ACTION_TASK_DELEGATE` or `ACTION_TASK_CHANGE_RESPONSIBLE` |
| Creator | `Stakeholder\UpdateCreatorCommand(taskId, creatorId, responsibleId, UpdateConfig)` | `ACTION_TASK_CHANGE_DIRECTOR` |

- The kernel checks these actions with before/after task models. Simple equivalent: `canSave($userId, new Entity\Task(id: $taskId, accomplices: …))` of `TaskAccessService`.
- Delegate resets status to pending and adds the previous responsible to auditors (unless already a member or the project is scrum).
- In `UpdateTaskCommand` the `accomplices`/`auditors` collections replace the whole list.

## Deadlines and time zones

- User-driven change: `Deadline\UpdateDeadlineCommand(taskId, deadlineTs, updateConfig, reason)`. It applies `DeadlinePolicy` (max changes, required reason, e.g. in flows) and returns an error when violated. `deadlineTs` in `UpdateTaskCommand` skips that policy: use it for system changes only.
- `ACTION_TASK_DEADLINE` honours the task flag `allowsChangeDeadline` (`ALLOW_CHANGE_DEADLINE`).
- Legacy array `DEADLINE` given as a string is parsed by `DateTime::createFromUserTime()`: the offset of the **session** user, not of the actor you pass. In agents and CLI there is no user, so it is server time. Pass `Main\Type\DateTime` objects or timestamps; `CTasks::Add()` also accepts `['SKIP_TIMEZONE' => ['DEADLINE']]`.

## Checklists

`Bitrix\Tasks\CheckList\Task\TaskCheckListFacade` checks rights itself (`ACTION_CHECKLIST_ADD`/`_EDIT`/`_TOGGLE`) and returns `Bitrix\Tasks\Util\Result` (`isSuccess()`, `getErrors()->getMessages()`, `getData()['ITEM']`).

```php
$root = \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::add($taskId, $userId, ['TITLE' => 'Release', 'PARENT_ID' => 0]);
if ($root->isSuccess()) {
    $rootId = (int)$root->getData()['ITEM']->getFields()['ID'];
    \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::add($taskId, $userId, ['TITLE' => 'Tag build', 'PARENT_ID' => $rootId]);
}

// complete one item the way REST does
$fields = \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::getList([], ['ID' => $itemId])[$itemId] ?? null;
if ($fields !== null) {
    $item = new \Bitrix\Tasks\CheckList\Internals\CheckList(0, $userId, \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::class, $fields);
    \Bitrix\Tasks\CheckList\Task\TaskCheckListFacade::complete($taskId, $userId, $item);
}
```

- `PARENT_ID => 0` creates a new checklist; items point to its id. Allowed keys: `TITLE`, `PARENT_ID`, `SORT_INDEX`, `IS_COMPLETE`, `IS_IMPORTANT`, `MEMBERS`, `ATTACHMENTS`.
- `getList($select, ['TASK_ID' => $taskId])` does not check rights (check `ACTION_TASK_READ` first); result is keyed by item id.
- Bulk without rights check: `V2\Public\Command\CheckList\CompleteCheckListItemsCommand(ids, userId)` / `RenewCheckListItemsCommand`.

## Tags and projects

- Tags: `tags: Entity\TagCollection::mapFromArray([['name' => 'urgent']])` (replaces the set); legacy `TAGS => ['urgent']`. Read with `TaskParams(tags: true)`, `$task->tags`.
- Project: `group: new Entity\Group(id: $groupId)` / `GROUP_ID`. Creating in a project needs the socialnetwork `tasks` → `create_tasks` permission, checked by `ACTION_TASK_SAVE`. List by `FieldsEnum::GroupId`.

## From a template

```php
$locator = \Bitrix\Main\DI\ServiceLocator::getInstance();
$task = $locator->get(\Bitrix\Tasks\V2\Public\Provider\Template\Task\TemplateToTaskProvider::class)
    ->get(new \Bitrix\Tasks\V2\Public\Provider\Params\Template\Task\TemplateToTaskParams(userId: $userId, templateId: $templateId));
if ($task !== null) { // null: no template or no read access
    $result = (new \Bitrix\Tasks\V2\Public\Command\Template\Task\AddTaskCommand(
        templateId: $templateId,
        taskData: $task->cloneWith(['deadlineTs' => $deadlineTs]),
        config: new \Bitrix\Tasks\V2\Internal\Service\Template\Task\Add\Config\AddTaskConfig(userId: $userId, withSubTasks: true),
    ))->run();
}
```

`taskData` is the full new task (the provider pre-fills it from the template); the command checks `canSave` itself.

## Comments

- New task card (tasks 25.700+ per REST docs): a comment is a message in the task chat. Send as a user: `Chat\SendMessageCommand(taskId, userId, new \Bitrix\Tasks\V2\Internal\Integration\Im\Entity\Message(text: $text))` (check `ACTION_TASK_READ`). Chat id: `(new \Bitrix\Tasks\V2\Public\Provider\ChatProvider())->getByTaskId($taskId)?->getId()`; then any `bitrix24-im` API with dialog `chat<ID>`.
- React to new comments: `im:OnAfterSendMessageExternalChatTasksTask` (event `AfterSendMessageEvent`, `getChat()->getEntityId()` = task id, `getMessage()`); runs after the response.
- `tasks:OnAfterCommentAdd` still fires for forum comments; for chat messages only REST handlers receive it.
- Forum comments (old card): `\CTaskCommentItem::add(\CTaskItem::getInstance($taskId, $userId), ['POST_MESSAGE' => $text])`.
