# Операции добавления, изменения и удаления

Порядок `launch()`: лимиты, права пользователя контекста, проверка запущенных БП, обработка полей (значения по умолчанию, стадия, суммы), обязательные поля, действия до сохранения, сохранение, атрибуты прав/поиск/дубли/счётчики, история/таймлайн, действия после сохранения (legacy-события, лента), pull, БП, роботы.

## Добавление, изменение, удаление

```php
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;
use Bitrix\Crm\Service\Context;

$factory = Container::getInstance()->getFactory(\CCrmOwnerType::Deal);
$context = (new Context())->setUserId($userId); // проверка прав, CREATED_BY/UPDATED_BY

$item = $factory->createItem([
    Item::FIELD_NAME_TITLE => 'Order #15',
    Item::FIELD_NAME_CATEGORY_ID => $categoryId,
    Item::FIELD_NAME_ASSIGNED => $userId,
]);
$result = $factory->getAddOperation($item, $context)->launch();
if (!$result->isSuccess()) {
    return $result; // отказ в правах, обязательные поля, отмена обработчиком
}
$id = $item->getId();

$item = $factory->getItem($id);
$item->setStageId($stageId);
$result = $factory->getUpdateOperation($item, $context)->launch();

// ОПАСНО: перенос в корзину, если включена для типа; иначе безвозвратное удаление.
$result = $factory->getDeleteOperation($item, $context)->launch();
```

- Без `$context` используется общий `Container::getInstance()->getContext()`: текущий `$USER`, в агенте/CLI/очереди — пользователь 0 и `CRM_ITEM_ADD_ACCESS_DENIED`. Создавай свой контекст, не меняй общий.
- `setScope(Context::SCOPE_TASK)` (также `SCOPE_AUTOMATION`, `SCOPE_REST`, `SCOPE_MANUAL`) задаёт источник изменения.
- Ключи `createItem()` — общие имена полей; применяются значения UF по умолчанию.
- Недопустимый `STAGE_ID` при изменении молча возвращается к старой стадии. При смене категории становится первой стадией новой категории.
- Смена категории при активных БП даёт `CRM_ITEM_HAS_RUNNING_WORKFLOWS`.
- Изменение без фактических изменений успешно и ничего не сохраняет: без действий и событий.
- Ошибки после сохранения входят в `Result`, но элемент уже записан.

## Отключение проверок

Вызывай методы операции до `launch()`. У каждого есть парные `enable*()`/`is*()`.

| Метод | Что отключает |
| --- | --- |
| `disableCheckAccess()` | права пользователя контекста |
| `disableCheckFields()` | обязательные поля, включая стадии |
| `disableCheckRequiredUserFields()` | обязательные UF |
| `disableCheckLimits()` | тарифные ограничения |
| `disableCheckWorkflows()` | проверку запущенных БП при смене категории |
| `disableAllChecks()` | пять проверок выше |
| `disableAutomation()` / `disableBizProc()` | роботов / автозапуск БП |
| `disableSaveToTimeline()` / `disableSaveToHistory()` | таймлайн / историю событий |
| `disableFieldProcession()` | значения по умолчанию, стадию, расчётные поля; обычно не отключать |
| `disableBeforeSaveActions()` / `disableAfterSaveActions()` | все действия, включая legacy-события и очистку кеша; обычно не отключать |

Для доверенного массового импорта допускается выборочное отключение проверок и автоматизации после определения прав и требований к данным. `disableAllChecks()` снимает также права и тарифные ограничения. События и таймлайн сохраняй, если замер не показал обоснованной необходимости отключения.

## Свои действия операции

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
            $result->addError(new Error('Заполните комментарий', 'VENDOR_COMMENT_REQUIRED'));
        }

        return $result;
    }
}

$operation = $factory->getUpdateOperation($item, $context);
$operation->addAction(Operation::ACTION_BEFORE_SAVE, new RequireComment());
$result = $operation->launch();
```

- `ACTION_BEFORE_SAVE` выполняется только при изменении Item. `isChanged()`/`remindActual()` дают различия; ошибка отменяет сохранение.
- В `ACTION_AFTER_SAVE` метод `$this->getItemBeforeSave()` даёт прежнее состояние, `$this->getContext()` — контекст. Ошибка передаётся вызывающему, отката нет.
- Третий аргумент `$sort` (по умолчанию 500) задаёт порядок; `removeAction($placement, $className)` удаляет действие ядра.
- `addAction()` меняет только текущий объект операции. Для записей интерфейса и REST подменяй фабрику (`rules/events-automation.md`).
