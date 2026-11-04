# Скидки и купоны

## Правила корзины

- Создание: старые `CSaleDiscount::Add()` / `Update()`, деревья `CONDITIONS`/`ACTIONS`, `PRIORITY` + `SORT`, `LAST_DISCOUNT`; полного API записи D7 нет. Удаление: `Internals\DiscountTable::delete()`, включая купоны, привязки, индекс скидки.
- Не вычисляй скидки вручную и не записывай итоговые цены.
- Корзина без заказа: `Discount::buildFromBasket()` + `calculate()` + `applyDiscount()` (`rules/basket.md`). Новый / сохранённый заказ: `Order::doFinalAction(true)`. Для его корзины `buildFromBasket()` запрещён.
- Применённые скидки: `$order->getDiscount()->getApplyResult()`; сохранённые — `OrderDiscount::loadResultFromDb($orderId)`, строки `Internals\OrderRulesTable`.

## Купоны

```php
use Bitrix\Sale\DiscountCouponsManager;

// init($mode, array $params = [], bool $clearStorage = false); ключи зависят от регистра
DiscountCouponsManager::init(DiscountCouponsManager::MODE_CLIENT); // userId необязателен
DiscountCouponsManager::reInit(DiscountCouponsManager::MODE_MANAGER, ['userId' => $userId]); // orderId запрещён
DiscountCouponsManager::reInit(DiscountCouponsManager::MODE_ORDER, ['userId' => $userId, 'orderId' => $orderId]);

DiscountCouponsManager::add($code); // true не означает применение
// пересчёт: doFinalAction(true) либо предварительный расчёт корзины
$coupon = DiscountCouponsManager::get(true, ['COUPON' => $code], true, true)[$code] ?? null;
$applied = $coupon !== null && $coupon['STATUS'] === DiscountCouponsManager::STATUS_APPLYED;
```

- После инициализации `init()` ничего не делает: меняй режим / пользователя через `reInit()`. `MODE_MANAGER` требует `userId`, запрещает `orderId` (`ERR_ORDER_ID_EXIST`). `MODE_ORDER` требует `userId`, `orderId > 0`. `oldUserId` переносит купоны после смены покупателя. Ошибки: `DiscountCouponsManager::isSuccess()` / `getErrors()`.
- **`add() === true` не означает применения скидки**: пересчитай, затем проверь `STATUS_APPLYED`.
- Купоны: `Internals\DiscountCouponTable`, `TYPE_ONE_ORDER`, `TYPE_MULTI_ORDER` + `MAX_USE`, `TYPE_BASKET_ROW`.
