# Orders: API choice, create, update, properties

## API choice

| Task | API |
| --- | --- |
| Create/change basket, order, payment, shipment | Object model `Bitrix\Sale\*`: validates, saves collections, fires events, writes history |
| Lists, reports, aggregates | `Bitrix\Sale\Internals\*Table` (`OrderTable`, `BasketTable`, `PaymentTable`, `ShipmentTable`), read-only for order data |
| Settings via code | `PersonTypeTable`, `OrderPropsTable`, `OrderPropsGroupTable`, `StatusTable` + `StatusLangTable` (writes allowed, in migrations) |
| Pick a configured service | `PaySystem\Manager`, `Delivery\Services\Manager`, `Cashbox\Manager`, `Services\Company\Manager`, `DiscountCouponsManager` |
| No full D7 replacement | `CSaleOrder::CanUserViewOrder()` etc. (rights), `CSaleOrderChange` (history read), `CSaleDiscount::Add/Update` (cart rules), `CSaleOrderUserProps` (buyer profiles), `CSaleUserAccount` (balance), `CSaleOrderTax` (tax rows) |

Never change an order via `OrderTable::update()` or create payments/shipments as raw rows. Don't mix legacy `CSale*` writes with a loaded `Order` in memory.

## Create (pipeline)

Order matters: basket → order → person type → basket in → properties → shipment → delivery calc → payment → `doFinalAction(true)` → sync payment `SUM` → re-check restrictions → `save()`.

```php
<?php declare(strict_types=1);

use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem\Manager as PaySystemManager;
use Bitrix\Sale\Services\Base\RestrictionManager;

$order = Order::create($siteId, $userId);  // currency: site's, else base
$order->setPersonTypeId($personTypeId);    // BEFORE getPropertyCollection(); not validated against site
$order->setBasket($orderableBasket);       // new (unsaved) order only; see rules/basket.md

$order->getPropertyCollection()->getItemByOrderPropertyCode('PHONE')?->setValue($phone); // setValue returns Result

$shipment = $order->getShipmentCollection()->createItem(); // system shipment already exists, never give it a service
foreach ($order->getBasket() as $basketItem) {
    $shipment->getShipmentItemCollection()->createItem($basketItem)->setQuantity($basketItem->getQuantity());
}
$deliveries = DeliveryManager::getRestrictedObjectsList($shipment);
$shipment->setDeliveryService($deliveries[$deliveryId] ?? throw new \RuntimeException('delivery unavailable'));
$order->getShipmentCollection()->calculateDelivery();

$payment = $order->getPaymentCollection()->createItem();
$payment->setField('SUM', $order->getPrice());
$allowed = PaySystemManager::getListWithRestrictions($payment, RestrictionManager::MODE_CLIENT);
isset($allowed[$paySystemId]) or throw new \RuntimeException('pay system unavailable');
$payment->setPaySystemService(PaySystemManager::getObjectById($paySystemId));

$order->doFinalAction(true);                   // discounts, taxes, totals; check its Result
$payment->setField('SUM', $order->getPrice()); // sync after final calc; re-check both restricted lists

$saveResult = $order->save();                  // isSuccess() AND getWarningMessages()
$orderId = $saveResult->getId();
```

- Payment and user shipment are optional on first save (digital goods, deferred flows); add them later on the loaded order.
- Integrations: store your own idempotency key (`XML_ID` is not unique-constrained).
- Load: `Order::load($id)`, `Order::loadByAccountNumber($number)`, `Order::loadByFilter([...])`. Lock while editing: `Order::lock($id)`, `Order::isLocked($id)`, `Order::unlock($id)`.

## Update

Work on one loaded object, save once. What to rerun after a change:

| Change | `calculateDelivery()` | `doFinalAction(true)` | Sync unpaid payment `SUM` |
| --- | --- | --- | --- |
| Status, cancel, mark, comment, tracking, allow delivery | – | – | – |
| Location/address used in restrictions | yes | yes | if price changed |
| Basket items/qty, delivery service/cost, shipment removal | yes | yes | if price changed |
| Coupon, discount, tax data | if delivery affected | yes | if price changed |

- Quantity down: `ShipmentItem::setQuantity()` first, then `BasketItem::setField('QUANTITY')`. Up: basket first, then shipment. Then refresh the item, recalc, save.
- Cancel: `setField('CANCELED', 'Y')` + `REASON_CANCELED`. Blocked while a paid payment or shipped shipment exists. `Order::delete()` is a service-only hard delete, never a customer refusal.
- `PERSON_TYPE_ID` change is a migration (property values are not remapped). `CURRENCY`/`USER_ID` can't change via `setField()`. Never write `SUM_PAID`/`PAYED` directly.

## Properties

Setting (`OrderPropsTable`, bound to a person type; `ENTITY_TYPE` ORDER/SHIPMENT) vs value in an order (`PropertyValueCollection`). Create settings (`OrderPropsGroupTable::add()` + `OrderPropsTable::add()`) in migrations, never during checkout.

- Find values: `getItemByOrderPropertyCode()` (first match), `getItemByOrderPropertyId()`, by role `getDeliveryLocation()`, `getPhone()`, `getUserEmail()`; groups `getGroups()`.
- `LOCATION` takes the location code, not a name. `ENUM` takes the variant `VALUE` (options: `$value->getPropertyObject()->getOptions()`); `MULTIPLE = Y` takes an array. Form input: `$order->getPropertyCollection()->setValuesFromPost($post, $files)` + `verify()`.
- Required check before save: for each value `isRequired()` + `checkRequiredValue($key, $value)`.
- Values save only with `Order::save()`; never write `OrderPropsValueTable`.
