# Остатки и права складов

## Остатки и склады

- Товар: `QUANTITY`, `QUANTITY_RESERVED`, `QUANTITY_TRACE`, `CAN_BUY_ZERO`, `AVAILABLE` в `ProductTable`.
- Склады: `StoreTable` + `StoreProductTable` (`STORE_ID`, `PRODUCT_ID`, `AMOUNT`, `QUANTITY_RESERVED`). Приход, перемещение и списание проводи складскими документами: `StoreDocumentTable`, проведение `CCatalogDocs::conductDocument($docId, $userId)`; при ошибке `false` + `$APPLICATION->GetException()`. Не меняй `AMOUNT` напрямую или через SQL.
- Права складского учёта с `crm`: применяй фильтр доступных пользователю складов:

```php
use Bitrix\Catalog\Access\AccessController;
use Bitrix\Catalog\Access\ActionDictionary;
use Bitrix\Catalog\StoreProductTable;

$access = AccessController::getCurrent()
    ->getEntityFilter(ActionDictionary::ACTION_STORE_VIEW, StoreProductTable::class) ?? [];
$rows = StoreProductTable::getList([
    'select' => ['STORE_ID', 'AMOUNT', 'QUANTITY_RESERVED'],
    'filter' => [['=PRODUCT_ID' => $elementId], $access], // вложенность не позволяет OR пользовательского фильтра обойти права
])->fetchAll();
```

- **Since catalog 26.400** REST `catalog.storeproduct.list/get` и `catalog.document.element.list` применяют этот фильтр. Для недоступных складов поля документов `STORE_FROM`/`STORE_TO`/`AMOUNT`/`PURCHASING_PRICE` равны `null`; фильтрация и сортировка по ним запрещены. Интеграция не должна на них полагаться. Остатки не фильтруются для администраторов и в старом режиме: `AccessController::getCurrent()->isLegacyAccessMode()` (**Since catalog 26.400**; раньше: `!ModuleManager::isModuleInstalled('crm')`). Старый REST возвращает все склады: фильтруй в интеграции.

