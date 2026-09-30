# Statuses, rights, history, events

## Statuses

- Order: `STATUS_ID`, initial `N`, final `F`, class `Bitrix\Sale\OrderStatus`. Shipment: own `STATUS_ID`, `DN` → `DF`, class `Bitrix\Sale\DeliveryStatus`. Dictionary `StatusTable` (`TYPE_ORDER`/`TYPE_SHIPMENT`) + `StatusLangTable` names.
- Allowed transitions: `OrderStatus::getAllowedUserStatuses($userId, $currentStatusId)`. Operations per status: `getStatusesUserCanDoOperations()`, `canGroupDoOperations()` (`view`, `update`, `delete`, `cancel`, `mark`, `payment`, `delivery`, `deduction`, `from`, `to`). Whether a status allows payment: `OrderStatus::isAllowPay($statusId)`.

## Rights

The object API **does not check rights**. Before acting on a user request check the concrete order with legacy `CSaleOrder`:

- `CSaleOrder::CanUserViewOrder($id, $groups, $userId)` — before `Order::load()`.
- `CSaleOrder::CanUserUpdateOrder($id, $groups, $siteId)` — pass `0` as ID for create.
- `CSaleOrder::CanUserCancelOrder()`, `CSaleOrder::CanUserMarkOrder()`, `CSaleOrder::CanUserDeleteOrder()`.
- `CSaleOrder::CanUserChangeOrderStatus($id, $statusId, $groups)`.
- `CSaleOrder::CanUserChangeOrderFlag($id, 'PERM_PAYMENT'|'PERM_DELIVERY'|'PERM_DEDUCTION', $groups)`.

Module access levels: `D` denied, `P` company binding, `U` order processing (still needs site and status-task grants), `W` full.

History is written by `OrderHistory` on save; read it via legacy `CSaleOrderChange::GetList()` (`'@TYPE' => ['ORDER_STATUS_CHANGED', …]`).

## Events

Register via `EventManager` in `init.php` or on module install (`bitrix-events`).

| Event | When / use |
| --- | --- |
| `OnSaleOrderBeforeSaved` | before save; may modify or deny |
| `OnSaleOrderSaved` | after save (`IS_NEW`, `IS_CHANGED`); result ignored |
| `OnSaleStatusOrderChange` (`VALUE`/`OLD_VALUE`), `OnSaleOrderPaid`, `OnSaleOrderCanceled`, `OnSaleStatusShipmentChange`, `OnShipmentDeducted`, `OnShipmentAllowDelivery`, `OnShipmentTrackingNumberChange` | deferred, after save |
| `OnSaleShipmentDelivery` | allow-delivery processed, before `Order::save()` completes |
| `OnBefore/On/OnAfter` + `Sale{BasketItem,Payment,Shipment,ShipmentItem,PropertyValue}SetField`, `OnSale*EntitySaved` | per entity |
| `OnSaleBasketItemBeforeSaved`, `OnSaleBasketItemSaved`, `OnSaleBasketItemRefreshData` | basket |
| `OnBeforeSaleOrderFinalAction`, `OnAfterSaleOrderFinalAction` | around `doFinalAction()` |
| `OnSaleOrderBeforeArchived` | before archiving |

- **Never call `$order->save()` from `OnSaleOrderSaved`**: recursion. Mutate in `OnSaleOrderBeforeSaved`, or queue a job that reloads the order (`bitrix-background-jobs`).
- `OnBefore*` handlers returning `EventResult::ERROR` surface as `setField()`/`save()` errors.
