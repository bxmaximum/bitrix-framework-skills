# Товары, инфоблоки каталога и SKU

## Выбор API

| Задача | API |
| --- | --- |
| Читать товары, цены, остатки | `ProductTable`, `PriceTable`, `StoreProductTable`, `CatalogIblockTable` (ORM) |
| Записывать поля товара: количество, тип, НДС | `Model\Product::add/update` пересчитывает `AVAILABLE`, тип и доступность родителя SKU, комплекты, подписки |
| Записывать цены | `Model\Price::add/update/delete` вычисляет `PRICE_SCALE`, проверяет валюту, пересчитывает цены родителя SKU |
| Регистрировать инфоблок каталога | `CCatalog::Add/Update` (API записи D7 нет) |
| Поддерживать старый код | `CCatalogProduct`, `CPrice`, вспомогательные методы `CCatalogSku` |

Не записывай напрямую через `ProductTable`/`PriceTable`: `PriceTable::add()` без `PRICE_SCALE` не проходит проверку; прямая запись обходит пересчёт доступности и SKU.

## Инфоблоки каталога и SKU

```php
\CCatalog::Add(['IBLOCK_ID' => $productIblockId]);                       // простой каталог
\CCatalog::Add([
    'IBLOCK_ID' => $offersIblockId,
    'PRODUCT_IBLOCK_ID' => $productIblockId,
    'SKU_PROPERTY_ID' => $skuPropertyId, // свойство E инфоблока предложений; USER_TYPE = PropertyTable::USER_TYPE_SKU, LINK_IBLOCK_ID = инфоблок товаров
]);
$info = \CCatalogSku::GetInfoByIBlock($iblockId); // CATALOG_TYPE, PRODUCT_IBLOCK_ID, IBLOCK_ID, SKU_PROPERTY_ID…
```

`CATALOG_TYPE`: `CCatalogSku::TYPE_CATALOG` (D, простой), `TYPE_PRODUCT` (P, только родители), `TYPE_OFFERS` (O, предложения), `TYPE_FULL` (X, товары и родители SKU). Предложения товаров: `\CCatalogSku::getOffersList($productIds, $iblockId)`.

Типы `ProductTable::TYPE_*`: `PRODUCT` 1, `SET` 2, `SKU` 3 (родитель предложений), `OFFER` 4, `FREE_OFFER` 5 (предложение без родителя), `EMPTY_SKU` 6, `SERVICE` 7 (без учёта остатков). В корзину добавляй предложение (`TYPE_OFFER`), а не родителя SKU.

```php
use Bitrix\Catalog\Model\Product;
use Bitrix\Catalog\ProductTable;

$result = Product::update($elementId, [ // новая строка: Product::add(['ID' => $elementId, 'TYPE' => ProductTable::TYPE_PRODUCT, …])
    'QUANTITY' => 10,
    'QUANTITY_TRACE' => ProductTable::STATUS_YES,
    'CAN_BUY_ZERO' => ProductTable::STATUS_NO,
]);
if (!$result->isSuccess()) { /* $result->getErrorMessages() */ }
```

