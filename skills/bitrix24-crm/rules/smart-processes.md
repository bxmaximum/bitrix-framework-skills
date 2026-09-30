# Smart processes (dynamic types)

## IDs

- `entityTypeId`: 128–191, or even numbers ≥ 1030 (`\CCrmOwnerType::UnlimitedTypeStart`). Check with `\CCrmOwnerType::isPossibleDynamicTypeId()`. Recycle-bin twins: 192–255 and odd ≥ 1031.
- Type `ID` (row of `b_crm_dynamic_type`) ≠ `entityTypeId`. UF entity uses the type `ID` (`CRM_5`), stages use `entityTypeId` (`DT1030_12:NEW`).
- Smart invoices (`\CCrmOwnerType::SmartInvoice` = 31), smart documents (36) and B2E documents (39) are dynamic-type based with fixed IDs; same Factory API.
- IDs differ between portals: give every type a `CODE`, store the code, resolve the ID at runtime.

## Lookup

```php
use Bitrix\Crm\Service\Container;

$container = Container::getInstance();
$type = $container->getTypeByEntityTypeId($entityTypeId); // ?Bitrix\Crm\Model\Dynamic\Type
$type = $container->getType($typeId);                    // by type ID

$type = null;
foreach ($container->getDynamicTypesMap()->getTypesCollection() as $candidate) {
    if ($candidate->getCode() === 'projects') {
        $type = $candidate;
        break;
    }
}
$factory = $type ? $container->getFactory($type->getEntityTypeId()) : null;
```

- `getTypesCollection()` is cached and holds all initialized types (incl. smart invoices); prefer it to `TypeTable::getList()` in hot paths.
- Features per type: `$factory->isCategoriesEnabled()`, `isStagesEnabled()`, `isAutomationEnabled()`, `isClientEnabled()`, `isObserversEnabled()`, `isLinkWithProductsEnabled()`, `isRecyclebinEnabled()`; or type getters `getIsCategoriesEnabled()`, `getIsStagesEnabled()`, ….

## Funnels and stages

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

- Stage ID formats: deals `NEW` (funnel 0) and `C{id}:NEW`; smart processes `DT{entityTypeId}_{categoryId}:NEW`. Read them, don't build them.
- Semantics: `PhaseSemantics::PROCESS` (`P`), `SUCCESS` (`S`), `FAILURE` (`F`).
- New funnel: `$factory->createCategory()->setName('B2B')->setSort(200)->save()` → `Result`. For user actions check `getUserPermissions()->category()->canAdd($category)` first.
- New stage (no D7 writer; REST `crm.status.add` does the same): `(new \CCrmStatus($factory->getStagesEntityId($categoryId)))->Add(['NAME' => 'Check', 'SORT' => 50, 'SEMANTICS' => PhaseSemantics::PROCESS])` returns ID or `false`; then `$factory->purgeStagesCache()`.

## Creating a type in code

No service method exists. Kernel code (`Crm\Controller\Type`, `Crm\Integration\AiAssistant\Tools\DynamicType\CreateDynamicTypeTool`) saves the ORM object; `TypeTable` events create the items table, indexes and the default funnel.

```php
use Bitrix\Crm\Restriction\RestrictionManager;
use Bitrix\Crm\Service\Container;

if (RestrictionManager::getDynamicTypesLimitRestriction()->isCreateTypeRestricted()) {
    return; // tariff limit reached
}
$dataClass = Container::getInstance()->getDynamicTypeDataClass();
$name = $dataClass::generateName('Projects'); // ?string
$type = $dataClass::createObject(); // reserves ENTITY_TYPE_ID
$type->setTitle('Projects');
$type->setName($name);
$type->setCode('projects');
$type->setIsCategoriesEnabled(true);
$type->setIsStagesEnabled(true);
$type->setIsAutomationEnabled(true);
$result = $type->save();
```

- Run it in a migration or module installer (`bitrix-sprint-migration`), idempotently: look the code up first.
- The controller additionally saves relations, conversion map and custom section; skip unless needed.
- Deleting a type drops its items table and data: DANGER, never in routine code.
