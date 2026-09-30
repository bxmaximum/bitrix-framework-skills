# Factory and Item: reading

## Entry points

```php
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Container;

\Bitrix\Main\Loader::requireModule('crm');
$container = Container::getInstance();
$factory = $container->getFactory(\CCrmOwnerType::Deal); // ?Factory
```

- Factories: Lead (1), Deal (2), Contact (3), Company (4), Quote (7), SmartInvoice (31), SmartDocument (36), smart processes; Order (14) only with `sale`. Old invoices (5) and activities (6) have none: `null`.
- `getFactory()` returns `null` for an unknown or deleted smart process: always check.
- `\CCrmOwnerType::ResolveID('DEAL')` ↔ `ResolveName(2)`; `isUseFactoryBasedApproach($entityTypeId)`.

## Reading

```php
$items = $factory->getItems([
    'select' => [Item::FIELD_NAME_ID, Item::FIELD_NAME_TITLE, Item::FIELD_NAME_STAGE_ID],
    'filter' => [
        '=' . Item::FIELD_NAME_ASSIGNED => $userId,
        '@' . Item::FIELD_NAME_CATEGORY_ID => [0, $categoryId],
    ],
    'order' => [Item::FIELD_NAME_ID => 'DESC'],
    'limit' => 50,
]); // Item[]

$item = $factory->getItem($id); // ?Item, all fields incl. contacts, products, observers
$item = $factory->getItem($id, [Item::FIELD_NAME_ID, Item::FIELD_NAME_STAGE_ID]);
$count = $factory->getItemsCount(['=' . Item::FIELD_NAME_ASSIGNED => $userId]);
```

- Parameters use `DataManager::getList()` syntax. Common names in `select`/`filter`/`order` keys are mapped to entity columns, operators included.
- `getItems()`/`getItem()`/`getItemsCount()` ignore rights. For user-facing data use `getItemsFilteredByPermissions()` (`rules/permissions.md`).
- Default `select` (`*`) loads UF, parent links, company, contacts, products, observers: narrow it for lists.
- Heavy read-only aggregates may use `$factory->getDataClass()::query()`. Never write through the data class.

## Item API

- `get()`, `set()`, `isChanged()`, `remindActual()` (value before unsaved changes), `getData()` (common names), `getCompatibleData()` (legacy keys), `isNew()`, `getId()`, `getEntityTypeId()`, `hasField()`.
- Magic accessors follow the constants: `getTitle()`, `setStageId()`, `getAssignedById()`, `getCategoryId()`, `getOpportunity()`, `isChangedStageId()`, `getCompanyId()`, `setContactIds([...])` (replaces contact bindings).
- Constant names differ from values: `FIELD_NAME_ASSIGNED` = `ASSIGNED_BY_ID`, `FIELD_NAME_PRODUCTS` = `PRODUCT_ROWS`, `FIELD_NAME_BEGIN_DATE` = `BEGINDATE`.
- Smart processes switch fields per type: call `hasField()` before optional fields.
- Dates are `Bitrix\Main\Type\Date`/`DateTime` objects.
- Parent link (relation must be configured): `$item->set(\Bitrix\Crm\Service\ParentFieldManager::getParentFieldName(\CCrmOwnerType::Deal), $dealId)` sets `PARENT_ID_2`.
- Products: `$item->setProductRowsFromArrays([['PRODUCT_NAME' => 'X', 'PRICE' => 100.0, 'QUANTITY' => 2]])` replaces all rows, returns `Result`; saved by the operation.
- Card URL: `$container->getRouter()->getItemDetailUrl($entityTypeId, $id)` (`?Uri`).

## Phones and e-mails (FM)

Lead, contact, company only (`$factory->isMultiFieldsEnabled()`).

```php
use Bitrix\Crm\Multifield;

$fm = $item->getFm(); // Multifield\Collection, a copy
$fm->add(
    (new Multifield\Value())
        ->setTypeId(Multifield\Type\Phone::ID)
        ->setValueType(Multifield\Type\Phone::VALUE_TYPE_WORK)
        ->setValue('+79990000000')
);
$item->setFm($fm); // persisted by the add/update operation

foreach ($item->getFm()->filterByType(Multifield\Type\Email::ID) as $value) {
    $emails[] = $value->getValue();
}
```

- `getFm()` returns a clone: always `setFm()` it back. `add()` skips equal values; `removeById($id)` removes one.
- Put `Item::FIELD_NAME_FM` into `getItems()` `select` to batch-load FM for a list.
- Types: `Phone`, `Email`, `Web`, `Im`, `Link` in `Multifield\Type`; value types as `VALUE_TYPE_*` constants (`WORK`, `MOBILE`, `HOME`, …).
