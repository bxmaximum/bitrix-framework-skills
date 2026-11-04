# Таймлайн и дела

Операции сами пишут создание, изменения и стадии в таймлайн (`disableSaveToTimeline()` отключает запись). Для собственных записей используй API ниже.

## Комментарий

```php
use Bitrix\Crm\Timeline\CommentController;
use Bitrix\Crm\Timeline\CommentEntry;

$commentId = CommentEntry::create([
    'TEXT' => $text, // BB-код; пустая строка вызывает ArgumentException
    'AUTHOR_ID' => $userId, // по умолчанию текущий пользователь
    'SETTINGS' => ['HAS_FILES' => 'N'],
    'BINDINGS' => [['ENTITY_TYPE_ID' => $entityTypeId, 'ENTITY_ID' => $entityId]],
]); // int; при неудаче 0

if ($commentId > 0) {
    CommentController::getInstance()->onCreate($commentId, [
        'COMMENT' => $text,
        'ENTITY_TYPE_ID' => $entityTypeId,
        'ENTITY_ID' => $entityId,
    ]); // обновление открытых карточек через push
}
```

- Внутри права не проверяются. Для пользовательского действия сначала проверяй `getUserPermissions()->item()->canUpdate($entityTypeId, $entityId)`.
- Несколько `BINDINGS` показывают комментарий в нескольких карточках.
- `CommentEntry::update($id, ['COMMENT' => $text])` и `CommentEntry::delete($id)` отправляют `OnAfterCrmTimelineComment{Update|Delete}`.

## Запланированное дело

```php
use Bitrix\Crm\Activity\Entity\ToDo;
use Bitrix\Crm\Activity\Provider\ToDo\ToDo as ToDoProvider;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Main\Type\DateTime;

$todo = (new ToDo(new ItemIdentifier($entityTypeId, $entityId), new ToDoProvider()))
    ->setSubject('Перезвонить')
    ->setDescription('Обсудить предложение')
    ->setDeadline((new DateTime())->add('+1 day'))
    ->setResponsibleId($userId)
    ->setCheckPermissions(false); // только системное задание; для пользователя оставь true

$result = $todo->save(); // Main\Result
$activityId = $todo->getId();
```

- Ответственный по умолчанию — пользователь контекста. `setCheckPermissions(true)` проверяет право изменения владельца.
- `(new ToDo($owner, new ToDoProvider()))->load($activityId)` возвращает `?ToDo`; `loadNearest()` загружает ближайшее открытое дело.
- Сохранение уже завершённого дела даёт `CAN_NOT_UPDATE_COMPLETED_TODO`.

## Другие дела

```php
$open = \Bitrix\Crm\ActivityTable::getList([
    'select' => ['ID', 'SUBJECT', 'DEADLINE', 'PROVIDER_ID', 'RESPONSIBLE_ID'],
    'filter' => [
        '=BINDINGS.OWNER_TYPE_ID' => $entityTypeId,
        '=BINDINGS.OWNER_ID' => $entityId,
        '=COMPLETED' => 'N',
    ],
])->fetchAll();

\CCrmActivity::Complete($activityId, true); // современного D7-аналога пока нет
```

- Читай через `ActivityTable`/`ActivityBindingTable`; одно дело привязывается к нескольким владельцам. Таблицы здесь только для чтения.
- Запись — через провайдеры `Activity\Provider\*` либо `\CCrmActivity::Add/Update/Delete($id, …, $checkPerms, $regEvent, $options)`. Это legacy API, но по нему ядро сохраняет звонки, письма и встречи. Не вставляй строки в `ActivityTable`.
- Финальная стадия сделки/элемента автоматически завершает открытые дела соответствующих провайдеров. Отключение: `disableActivitiesAutocompletion()` операции.


**Since crm 26.1800** удалены провайдер `CRM_DOCUMENT` и `DocumentGeneratorManager::createDocumentActivity()`, `actualizeDocumentImmediately()`, `enqueueItemScheduledDocumentsForActualization()`. Старые документные дела недоступны; `CreateActivity=Y` действия БП больше не создаёт дело. Не используй удалённые методы. Для новой задачи планируй документ и обычное дело отдельными операциями; на старой версии учитывай прежнюю связь. <!-- verify:negative -->
