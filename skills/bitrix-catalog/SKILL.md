---
name: bitrix-catalog
description: 'Trade catalog on top of iblocks — catalog iblocks, product types, SKU/offers, price types and prices (Model\Price), stock and stores, store rights. Use when writing products, prices or stock, or reading them for storefront and integrations.'
---

# Trade catalog (`catalog`)

Baseline: main 23.0+ · Verified: catalog 26.400.0, main 26.800.0

Product ID = iblock element ID. Needs `iblock` + `catalog` loaded. Cart, orders, payments → `bitrix-sale`; elements and properties → `bitrix-iblocks`.

## API choice

| Need | Use |
| --- | --- |
| Read product/price/store rows | `ProductTable`, `PriceTable`, `StoreProductTable`, `CatalogIblockTable` (ORM) |
| Write product fields (qty, type, VAT) | `Model\Product::add/update` — recalculates `AVAILABLE`, parent SKU type/availability, sets, subscriptions |
| Write prices | `Model\Price::add/update/delete` — computes `PRICE_SCALE`, validates currency, recounts parent SKU prices |
| Register catalog iblock | `CCatalog::Add/Update` (no D7 writer) |
| Legacy, compat only | `CCatalogProduct`, `CPrice`, `CCatalogSku` helpers |

Never write `ProductTable`/`PriceTable` directly: `PriceTable::add()` without `PRICE_SCALE` fails validation, and direct writes skip availability and SKU recalculation.

## Catalog iblocks and SKU

```php
\CCatalog::Add(['IBLOCK_ID' => $productIblockId]);                       // simple catalog
\CCatalog::Add([
    'IBLOCK_ID' => $offersIblockId,
    'PRODUCT_IBLOCK_ID' => $productIblockId,
    'SKU_PROPERTY_ID' => $skuPropertyId, // E property on the offers iblock, USER_TYPE = PropertyTable::USER_TYPE_SKU, LINK_IBLOCK_ID = products
]);
$info = \CCatalogSku::GetInfoByIBlock($iblockId); // CATALOG_TYPE, PRODUCT_IBLOCK_ID, IBLOCK_ID, SKU_PROPERTY_ID…
```

`CATALOG_TYPE`: `CCatalogSku::TYPE_CATALOG` (D, plain), `TYPE_PRODUCT` (P, parents only), `TYPE_OFFERS` (O, offers iblock), `TYPE_FULL` (X, products + SKU parents). Offers of products: `\CCatalogSku::getOffersList($productIds, $iblockId)`.

Product types `ProductTable::TYPE_*`: `PRODUCT` 1, `SET` 2, `SKU` 3 (parent with offers), `OFFER` 4, `FREE_OFFER` 5 (offer without parent), `EMPTY_SKU` 6, `SERVICE` 7 (no stock tracking). Buy the offer (`TYPE_OFFER`), never the SKU parent.

```php
use Bitrix\Catalog\Model\Product;
use Bitrix\Catalog\ProductTable;

$result = Product::update($elementId, [ // Product::add(['ID' => $elementId, 'TYPE' => ProductTable::TYPE_PRODUCT, …]) for a new row
    'QUANTITY' => 10,
    'QUANTITY_TRACE' => ProductTable::STATUS_YES,
    'CAN_BUY_ZERO' => ProductTable::STATUS_NO,
]);
if (!$result->isSuccess()) { /* $result->getErrorMessages() */ }
```

## Prices

Price types: `GroupTable` (`b_catalog_group`); base type `GroupTable::getBasePriceTypeId()` (cached, `?int`). Who may see/buy by type: `GroupAccessTable`.

```php
use Bitrix\Catalog\GroupTable;
use Bitrix\Catalog\Model\Price;
use Bitrix\Catalog\PriceTable;

$result = Price::add([
    'PRODUCT_ID' => $elementId,
    'CATALOG_GROUP_ID' => GroupTable::getBasePriceTypeId(),
    'PRICE' => 1990.00,
    'CURRENCY' => 'RUB',   // must exist in the currency module
]);
if (!$result->isSuccess()) { /* $result->getErrorMessages() */ }

$prices = PriceTable::getList([
    'select' => ['ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY', 'QUANTITY_FROM', 'QUANTITY_TO'],
    'filter' => ['=PRODUCT_ID' => $elementId],
])->fetchAll();
```

VAT lives on the product (`VAT_ID`, `VAT_INCLUDED`); rates in `VatTable`. Final buyer prices and discounts are computed by `sale` (`bitrix-sale`), not by reading `PriceTable`.

## Stock and stores

- Product level: `QUANTITY`, `QUANTITY_RESERVED`, `QUANTITY_TRACE`, `CAN_BUY_ZERO`, `AVAILABLE` on `ProductTable`.
- Stores: `StoreTable` + `StoreProductTable` (`STORE_ID`, `PRODUCT_ID`, `AMOUNT`, `QUANTITY_RESERVED`). Warehouse operations (receipt, move, write-off) go through store documents (`StoreDocumentTable`, conduct with `CCatalogDocs::conductDocument($docId, $userId)`, `false` + `$APPLICATION->GetException()` on error), not direct `AMOUNT` edits or SQL.
- Store rights (inventory management with `crm`): mirror what the user may see:

```php
use Bitrix\Catalog\Access\AccessController;
use Bitrix\Catalog\Access\ActionDictionary;
use Bitrix\Catalog\StoreProductTable;

$access = AccessController::getCurrent()
    ->getEntityFilter(ActionDictionary::ACTION_STORE_VIEW, StoreProductTable::class) ?? [];
$rows = StoreProductTable::getList([
    'select' => ['STORE_ID', 'AMOUNT', 'QUANTITY_RESERVED'],
    'filter' => [['=PRODUCT_ID' => $elementId], $access], // nested: an OR in user filter cannot bypass access
])->fetchAll();
```

- **Since catalog 26.400** REST `catalog.storeproduct.list/get` and `catalog.document.element.list` apply this filter. Document rows of forbidden stores return `STORE_FROM`/`STORE_TO`/`AMOUNT`/`PURCHASING_PRICE` = `null`, and these fields cannot be filtered or sorted. Integrations must not rely on them. Stock rows are not filtered for admins and in legacy mode: `AccessController::getCurrent()->isLegacyAccessMode()` (**Since catalog 26.400**; before: `!ModuleManager::isModuleInstalled('crm')`). Older versions return all stores over REST: filter in the integration.

## Checklist

- [ ] `iblock` + `catalog` loaded; catalog iblocks registered via `CCatalog::Add`, SKU with `PRODUCT_IBLOCK_ID` + `SKU_PROPERTY_ID`.
- [ ] Product and price writes via `Model\Product` / `Model\Price`, every `Result` checked.
- [ ] Base price type via `GroupTable::getBasePriceTypeId()`, no hardcoded IDs.
- [ ] Offers (not SKU parents) go to the basket.
- [ ] Stock read with the store access filter where users are restricted; no raw SQL.
- [ ] Batch reads (collect IDs, one query); no `CCatalogProduct::GetByID` in loops.
