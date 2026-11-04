# Корзина и Fuser

## Fuser: владелец корзины

Анонимная и авторизованная корзины привязаны к **Fuser** (`Bitrix\Sale\Fuser`), а не `USER_ID`.

```php
use Bitrix\Sale\Fuser;

$fuserId = Fuser::getId();                // создаёт, если нет
$fuserId = Fuser::getId(true);            // без создания: null, если нет
$fuserId = Fuser::getIdByUserId($userId); // false при ошибке поиска / создания
```

`USER_ID` — учётная запись сайта, обязательна у сохранённого заказа; `FUSER_ID` — владелец корзины. Не подменяй один другим.

## Добавление в корзину

```php
<?php declare(strict_types=1);

use Bitrix\Catalog\Product\Basket as CatalogBasket;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Basket\RefreshFactory;
use Bitrix\Sale\Fuser;

$basket = Basket::loadItemsForFUser(Fuser::getId(), $siteId); // только строки ORDER_ID = null

// Для товаров каталога: задаёт модуль, поставщика и данные товара
$r = CatalogBasket::addProductToBasket($basket, ['PRODUCT_ID' => $productId, 'QUANTITY' => 1], ['SITE_ID' => $siteId]);
// Объединяет с существующей строкой; четвёртый аргумент USE_MERGE = N создаёт отдельную

// Ручной вариант
$item = $basket->createItem('catalog', $productId);
$item->setFields(['QUANTITY' => 1, 'PRODUCT_PROVIDER_CLASS' => CatalogBasket::getDefaultProviderName()]);
$basket->refresh(RefreshFactory::createSingle($item->getBasketCode())); // поставщик заполняет PRICE, CURRENCY, NAME, НДС, вес

$result = $basket->save(); // только для корзины БЕЗ привязки к заказу
```

- `PRICE`/`CURRENCY` товара каталога заполняет поставщик. Своя цена: `CUSTOM_PRICE => 'Y'` + `PRICE` + `CURRENCY`.
- Для SKU в `PRODUCT_ID` передавай **ID предложения**, не родителя. Перед добавлением проверь, что элемент — товар (`Bitrix\Catalog\ProductTable`).
- Корзина сохранённого заказа: `$order->getBasket()`, сохранение `Order::save()`. Не используй для неё `loadItemsForFUser()` / `$basket->save()`.
- До создания заказа: `$basket->refresh()` (`refreshData()` устарел), затем `$basket->getOrderableItems()` — только доступные для покупки и не отложенные позиции.
- Свойства позиции: `$item->getPropertyCollection()->createItem()` / `redefine()`. Цены: `getPrice()`, `getBasePrice()`, `getPriceWithVat()`, `getDiscountPrice()`.

## Предварительный расчёт скидок

```php
use Bitrix\Sale\Discount;

$discount = Discount::buildFromBasket($basket, new Discount\Context\Fuser($basket->getFUserId()));
$calc = $discount->calculate();
if ($calc->isSuccess()) {
    $basket->applyDiscount($calc->getData()['BASKET_ITEMS'] ?? []); // тот же путь, что в Order::doFinalAction()
}
// показать без изменения корзины: $discount->getShowPrices()['BASKET'][$basketCode]
```

Не вызывай `buildFromBasket()` для корзины заказа: пересчёт через `Order::doFinalAction(true)` (`rules/discounts.md`).
