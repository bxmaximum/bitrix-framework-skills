# Смарт-процессы: динамические типы

## Идентификаторы

- `entityTypeId`: 128–191 либо чётные от 1030 (`\CCrmOwnerType::UnlimitedTypeStart`). Проверка: `\CCrmOwnerType::isPossibleDynamicTypeId()`. Копии корзины: 192–255 и нечётные от 1031.
- `ID` строки `b_crm_dynamic_type` отличается от `entityTypeId`. UF использует `ID` типа (`CRM_5`), стадия — `entityTypeId` (`DT1030_12:NEW`).
- Смарт-счета (`\CCrmOwnerType::SmartInvoice` = 31), смарт-документы (36) и документы КЭДО (39) построены на динамических типах с постоянными ID; API Factory общий.
- ID между порталами различаются: задавай типу `CODE`, храни код, определяй ID при выполнении.

## Поиск

```php
use Bitrix\Crm\Service\Container;

$container = Container::getInstance();
$type = $container->getTypeByEntityTypeId($entityTypeId); // ?Bitrix\Crm\Model\Dynamic\Type
$type = $container->getType($typeId);                    // по ID типа

$type = null;
foreach ($container->getDynamicTypesMap()->getTypesCollection() as $candidate) {
    if ($candidate->getCode() === 'projects') {
        $type = $candidate;
        break;
    }
}
$factory = $type ? $container->getFactory($type->getEntityTypeId()) : null;
```

- `getTypesCollection()` кешируется и содержит все инициализированные типы, включая смарт-счета. В частых обращениях предпочтительнее `TypeTable::getList()`.
- Возможности типа: `$factory->isCategoriesEnabled()`, `isStagesEnabled()`, `isAutomationEnabled()`, `isClientEnabled()`, `isObserversEnabled()`, `isLinkWithProductsEnabled()`, `isRecyclebinEnabled()`; либо методы типа `getIsCategoriesEnabled()`, `getIsStagesEnabled()`, ….

## Воронки и стадии

```php
use Bitrix\Crm\PhaseSemantics;

$categories = $factory->getCategories();        // Category[]
$default = $factory->getDefaultCategory();      // ?Category
$b2b = $factory->getCategoryByCode('b2b');      // ?Category
foreach ($factory->getStages($categoryId) as $stage) { // EO_Status_Collection
    $stage->getStatusId(); $stage->getName(); $stage->getSemantics();
}
$wonStageId = $factory->getSuccessfulStage($categoryId)?->getStatusId();
$isFinal = $factory->getStageSemantics($item->getStageId()) !== PhaseSemantics::PROCESS;
```

- Форматы стадий: сделка `NEW` (воронка 0), `C{id}:NEW`; смарт-процесс `DT{entityTypeId}_{categoryId}:NEW`. Читай ID, не собирай строку вручную.
- Семантика: `PhaseSemantics::PROCESS` (`P`), `SUCCESS` (`S`), `FAILURE` (`F`).
- Создание воронки: `$factory->createCategory()->setName('B2B')->setSort(200)->save()` возвращает `Result`. Для пользовательского действия сначала проверяй `getUserPermissions()->category()->canAdd($category)`.
- Для записи стадии пока нужен legacy API; REST `crm.status.add` использует тот же путь: `(new \CCrmStatus($factory->getStagesEntityId($categoryId)))->Add(['NAME' => 'Проверка', 'SORT' => 50, 'SEMANTICS' => PhaseSemantics::PROCESS])`. Результат — ID либо `false`; затем `$factory->purgeStagesCache()`.

## Создание типа из PHP

В проверенном ядре нет отдельного сервиса создания типа. `Crm\Controller\Type` и `Crm\Integration\AiAssistant\Tools\DynamicType\CreateDynamicTypeTool` сохраняют ORM-объект. События `TypeTable` создают таблицу элементов, индексы, воронку по умолчанию.

```php
use Bitrix\Crm\Restriction\RestrictionManager;
use Bitrix\Crm\Service\Container;

if (RestrictionManager::getDynamicTypesLimitRestriction()->isCreateTypeRestricted()) {
    return; // достигнут тарифный лимит
}
$dataClass = Container::getInstance()->getDynamicTypeDataClass();
$name = $dataClass::generateName('Projects'); // ?string
$type = $dataClass::createObject(); // резервирует ENTITY_TYPE_ID
$type->setTitle('Projects');
$type->setName($name);
$type->setCode('projects');
$type->setIsCategoriesEnabled(true);
$type->setIsStagesEnabled(true);
$type->setIsAutomationEnabled(true);
$result = $type->save();
```

- Выполняй идемпотентно в миграции/установщике (`bitrix-sprint-migration`): сначала найди тип по коду.
- Контроллер дополнительно сохраняет связи, карту конвертации и свой раздел. Делай это только при необходимости.
- ОПАСНО: удаление типа удаляет таблицу элементов и данные. Не выполняй в обычном прикладном коде.


**Since crm 26.1800** стадии/статусы поддерживают `DESCRIPTION` до 65535 байт с BB-кодами. Записывай строку через `CCrmStatus` либо `crm.status.add/update`, с проверкой прав настройки. На старом модуле храни регламент отдельно от стадии.
