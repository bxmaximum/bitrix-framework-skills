# Права

```php
use Bitrix\Crm\Service\Container;

$perms = Container::getInstance()->getUserPermissions($userId); // null — пользователь контекста
```

Один кешируемый экземпляр на пользователя в пределах запроса.

| Проверка | Вызов |
| --- | --- |
| Администратор портала/CRM | `$perms->isAdmin()`, `$perms->isCrmAdmin()` |
| Администратор типа/воронки | `$perms->isAdminForEntity($entityTypeId, $categoryId)` |
| Доступ к типу | `$perms->entityType()->canReadItems($entityTypeId)`, `canAddItems()`, `canUpdateItems()`, `canDeleteItems()`, `canExportItems()`, `canImportItems()` |
| Доступ в воронке | `$perms->entityType()->canReadItemsInCategory($entityTypeId, $categoryId)`, `canAddItemsInCategory()`, `canUpdateItemsInCategory()`, `canDeleteItemsInCategory()` |
| Элемент по ID | `$perms->item()->canRead($entityTypeId, $id)`, `canUpdate()`, `canDelete()` |
| Загруженный элемент | `$perms->item()->canReadItem($item)`, `canAddItem($item)`, `canUpdateItem($item)`, `canDeleteItem($item)` |
| Смена стадии | `$perms->item()->canChangeStage(ItemIdentifier $identifier, $fromStageId, $toStageId)` |
| Права стадии | `$perms->stage()->canAddInStage($entityTypeId, $categoryId, $stageId)`, `canUpdateInStage()`, `canReadInStage()` |
| Воронки | `$perms->category()->canAdd($category)`, `canUpdate()`, `canDelete()`, `getAvailableForReadingCategoriesIds($entityTypeId)` |
| Настройки смарт-процесса | `$perms->dynamicType()->canAdd()`, `canUpdate($entityTypeId)` |

`ItemIdentifier` — `Bitrix\Crm\ItemIdentifier`: `new ItemIdentifier($entityTypeId, $id)` либо `ItemIdentifier::createByItem($item)`.

## Списки

```php
use Bitrix\Crm\Service\UserPermissions;

$items = $factory->getItemsFilteredByPermissions(
    ['select' => $select, 'filter' => $filter, 'order' => $order, 'limit' => 20],
    $userId,
    UserPermissions::OPERATION_READ, // OPERATION_UPDATE, OPERATION_DELETE, OPERATION_EXPORT
);
$total = $factory->getItemsCountFilteredByPermissions($filter, $userId);
```

- Пользователь 0 получает `[]`. Обычный `getItems()` не фильтрует по правам.
- В своём ORM-запросе таблицы CRM вызов `$perms->itemsList()->applyAvailableItemsFilter($filter, [(new PermissionEntityTypeHelper($entityTypeId))->getPermissionEntityTypeForCategory($categoryId)])` добавляет подзапрос доступа по `ID`. Класс: `Bitrix\Crm\Category\PermissionEntityTypeHelper`.

## Правила

- Запись через операции проверяет права пользователя контекста. Чтение, свои действия и кнопки интерфейса проверяй отдельно.
- Проверяй инициатора, а не технического пользователя: один реальный ID в `getUserPermissions()` и `Context`.
- В `UserPermissions` устарели: `checkReadPermissions()`, `checkAddPermissions()`, `checkUpdatePermissions()`, `checkDeletePermissions()`, `canReadType()`, `canUpdateType()`, `canWriteConfig()`, `canReadItem()`, `canUpdateItem()`, `getCrmPermissions()`. В старом ядре они нужны как запасной путь при отсутствии `item()`/`entityType()`. Наличие проверяй по исходнику версии, срок удаления в docblock не гарантирует удаление.
- `CCrmPerms`, `CCrmDeal::CheckReadPermission()`, `CCrmAuthorizationHelper::CheckUpdatePermission()` оставляй для совместимости.
