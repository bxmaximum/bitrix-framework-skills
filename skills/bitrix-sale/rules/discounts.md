# Discounts and coupons

## Cart rules

- Create via legacy `CSaleDiscount::Add()` / `Update()` (`CONDITIONS`/`ACTIONS` trees, `PRIORITY` + `SORT`, `LAST_DISCOUNT`); no full D7 writer. Delete via `Internals\DiscountTable::delete()` (also removes coupons, bindings and the discount index).
- Never compute discounts by hand or write final prices.
- Calculation: standalone basket → `Discount::buildFromBasket()` + `calculate()` + `applyDiscount()` (`rules/basket.md`). Saved or new order → `Order::doFinalAction(true)`. Never `buildFromBasket()` on an order basket.
- Applied result: `$order->getDiscount()->getApplyResult()`; saved orders `OrderDiscount::loadResultFromDb($orderId)`, rows in `Internals\OrderRulesTable`.

## Coupons

```php
use Bitrix\Sale\DiscountCouponsManager;

// signature: init($mode, array $params = [], bool $clearStorage = false); keys are case-sensitive
DiscountCouponsManager::init(DiscountCouponsManager::MODE_CLIENT);                                   // userId optional
DiscountCouponsManager::reInit(DiscountCouponsManager::MODE_MANAGER, ['userId' => $userId]);          // no orderId allowed
DiscountCouponsManager::reInit(DiscountCouponsManager::MODE_ORDER, ['userId' => $userId, 'orderId' => $orderId]);

DiscountCouponsManager::add($code);             // true ≠ applied
// … recalc: doFinalAction(true) or the basket preview …
$coupon = DiscountCouponsManager::get(true, ['COUPON' => $code], true, true)[$code] ?? null;
$applied = $coupon !== null && $coupon['STATUS'] === DiscountCouponsManager::STATUS_APPLYED;
```

- `init()` is a no-op once initialized in the request: use `reInit()` to switch mode or user. `MODE_MANAGER` needs `userId` and rejects `orderId` (`ERR_ORDER_ID_EXIST`). `MODE_ORDER` needs `userId` and `orderId > 0`. `oldUserId` migrates coupons after a buyer change. Errors: `DiscountCouponsManager::isSuccess()` / `getErrors()`.
- **`add() === true` does not mean the discount applied**: recalc, then check `STATUS_APPLYED`.
- Coupon rows: `Internals\DiscountCouponTable` (`TYPE_ONE_ORDER`, `TYPE_MULTI_ORDER` + `MAX_USE`, `TYPE_BASKET_ROW`).
