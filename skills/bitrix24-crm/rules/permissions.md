# Permissions

```php
use Bitrix\Crm\Service\Container;

$perms = Container::getInstance()->getUserPermissions($userId); // null → context user
```

One cached instance per user per hit.

| Question | Call |
| --- | --- |
| Portal admin, CRM admin | `$perms->isAdmin()`, `$perms->isCrmAdmin()` |
| Admin of a type or funnel | `$perms->isAdminForEntity($entityTypeId, $categoryId)` |
| Any access to a type | `$perms->entityType()->canReadItems($entityTypeId)`, `canAddItems()`, `canUpdateItems()`, `canDeleteItems()`, `canExportItems()`, `canImportItems()` |
| Same inside a funnel | `$perms->entityType()->canReadItemsInCategory($entityTypeId, $categoryId)`, `canAddItemsInCategory()`, `canUpdateItemsInCategory()`, `canDeleteItemsInCategory()` |
| One item by ID | `$perms->item()->canRead($entityTypeId, $id)`, `canUpdate()`, `canDelete()` |
| Loaded item | `$perms->item()->canReadItem($item)`, `canAddItem($item)`, `canUpdateItem($item)`, `canDeleteItem($item)` |
| Stage move | `$perms->item()->canChangeStage(ItemIdentifier $identifier, $fromStageId, $toStageId)` |
| Stage rights | `$perms->stage()->canAddInStage($entityTypeId, $categoryId, $stageId)`, `canUpdateInStage()`, `canReadInStage()` |
| Funnels | `$perms->category()->canAdd($category)`, `canUpdate()`, `canDelete()`, `getAvailableForReadingCategoriesIds($entityTypeId)` |
| Smart process settings | `$perms->dynamicType()->canAdd()`, `canUpdate($entityTypeId)` |

`ItemIdentifier` is `Bitrix\Crm\ItemIdentifier`: `new ItemIdentifier($entityTypeId, $id)` or `ItemIdentifier::createByItem($item)`.

## Lists

```php
use Bitrix\Crm\Service\UserPermissions;

$items = $factory->getItemsFilteredByPermissions(
    ['select' => $select, 'filter' => $filter, 'order' => $order, 'limit' => 20],
    $userId,
    UserPermissions::OPERATION_READ, // OPERATION_UPDATE, OPERATION_DELETE, OPERATION_EXPORT
);
$total = $factory->getItemsCountFilteredByPermissions($filter, $userId);
```

- User 0 gets `[]`. Plain `getItems()` never filters.
- Own ORM query on a CRM table: `$perms->itemsList()->applyAvailableItemsFilter($filter, [(new PermissionEntityTypeHelper($entityTypeId))->getPermissionEntityTypeForCategory($categoryId)])` adds an access subquery on `ID` (`Bitrix\Crm\Category\PermissionEntityTypeHelper`).

## Rules

- Operations check the context user's rights on write. Check reads, custom controller actions and UI buttons yourself.
- Check the acting user, not a technical one: pass the real user ID to both `getUserPermissions()` and `Context`.
- Deprecated on `UserPermissions` but still present in 26.800 (docblocks say removal "after March 2025"): `checkReadPermissions()`, `checkAddPermissions()`, `checkUpdatePermissions()`, `checkDeletePermissions()`, `canReadType()`, `canUpdateType()`, `canWriteConfig()`, `canReadItem()`, `canUpdateItem()`, `getCrmPermissions()`. Use them only as a fallback on older crm builds that lack `item()`/`entityType()`.
- `CCrmPerms`, `CCrmDeal::CheckReadPermission()`, `CCrmAuthorizationHelper::CheckUpdatePermission()`: compat only.
