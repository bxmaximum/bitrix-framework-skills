# Basket and Fuser

## Fuser (basket owner)

Anonymous and authorized baskets are keyed by **Fuser** (`Bitrix\Sale\Fuser`), not `USER_ID`.

```php
use Bitrix\Sale\Fuser;

$fuserId = Fuser::getId();                // creates if missing
$fuserId = Fuser::getId(true);            // skip create → null if none
$fuserId = Fuser::getIdByUserId($userId); // false if it cannot resolve/create
```

`USER_ID` (site account, required on a saved order) and `FUSER_ID` (basket owner) are different; never substitute one for the other.

## Add to basket

```php
<?php declare(strict_types=1);

use Bitrix\Catalog\Product\Basket as CatalogBasket;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Basket\RefreshFactory;
use Bitrix\Sale\Fuser;

$basket = Basket::loadItemsForFUser(Fuser::getId(), $siteId); // only rows with ORDER_ID = null

// Preferred for catalog products: sets module, provider and product data
$r = CatalogBasket::addProductToBasket($basket, ['PRODUCT_ID' => $productId, 'QUANTITY' => 1], ['SITE_ID' => $siteId]);
// merges into an existing row; 4th arg ['USE_MERGE' => 'N'] for a separate row

// Manual alternative
$item = $basket->createItem('catalog', $productId);
$item->setFields(['QUANTITY' => 1, 'PRODUCT_PROVIDER_CLASS' => CatalogBasket::getDefaultProviderName()]);
$basket->refresh(RefreshFactory::createSingle($item->getBasketCode())); // provider fills PRICE, CURRENCY, NAME, VAT, weight

$result = $basket->save(); // only for a basket NOT bound to an order
```

- Don't set `PRICE`/`CURRENCY` for catalog products; the provider does. Own pricing: `CUSTOM_PRICE => 'Y'` + `PRICE` + `CURRENCY`.
- With SKUs put the **offer ID** in `PRODUCT_ID`, never the parent. Check the element is a product (`Bitrix\Catalog\ProductTable`) before adding.
- Basket of a saved order: `$order->getBasket()`, saved by `Order::save()`. Never `loadItemsForFUser()` / `$basket->save()` for it.
- Before creating an order: `$basket->refresh()` (`refreshData()` is deprecated), then `$basket->getOrderableItems()` → a basket with only purchasable, non-delayed items.
- Item properties: `$item->getPropertyCollection()->createItem()` / `redefine()`. Prices: `getPrice()`, `getBasePrice()`, `getPriceWithVat()`, `getDiscountPrice()`.

## Discount preview before checkout

```php
use Bitrix\Sale\Discount;

$discount = Discount::buildFromBasket($basket, new Discount\Context\Fuser($basket->getFUserId()));
$calc = $discount->calculate();
if ($calc->isSuccess()) {
    $basket->applyDiscount($calc->getData()['BASKET_ITEMS'] ?? []); // same data path as Order::doFinalAction()
}
// display prices without touching the basket: $discount->getShowPrices()['BASKET'][$basketCode]
```

Never use `buildFromBasket()` on an order-bound basket: saved orders recalc via `Order::doFinalAction(true)` (`rules/discounts.md`).
