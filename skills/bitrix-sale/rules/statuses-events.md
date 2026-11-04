# Статусы, права, история, события

## Статусы

- Заказ: `STATUS_ID`, начальный N, конечный F, `Bitrix\Sale\OrderStatus`. Отгрузка: свой `STATUS_ID`, DN → DF, `Bitrix\Sale\DeliveryStatus`. Справочник `StatusTable` (`TYPE_ORDER`/`TYPE_SHIPMENT`), названия `StatusLangTable`.
- Переходы: `OrderStatus::getAllowedUserStatuses($userId, $currentStatusId)`. Операции статуса: `getStatusesUserCanDoOperations()`, `canGroupDoOperations()` (`view`, `update`, `delete`, `cancel`, `mark`, `payment`, `delivery`, `deduction`, `from`, `to`). Разрешение оплаты: `OrderStatus::isAllowPay($statusId)`.

## Права

Объектный API **не проверяет права**. Перед действием по запросу проверь конкретный заказ старым `CSaleOrder`:

- `CSaleOrder::CanUserViewOrder($id, $groups, $userId)` — до `Order::load()`.
- `CSaleOrder::CanUserUpdateOrder($id, $groups, $siteId)`; создание — ID 0.
- `CSaleOrder::CanUserCancelOrder()`, `CSaleOrder::CanUserMarkOrder()`, `CSaleOrder::CanUserDeleteOrder()`.
- `CSaleOrder::CanUserChangeOrderStatus($id, $statusId, $groups)`.
- `CSaleOrder::CanUserChangeOrderFlag($id, 'PERM_PAYMENT'|'PERM_DELIVERY'|'PERM_DEDUCTION', $groups)`.

Уровни модуля: D — запрет, P — привязка компании, U — обработка (также нужны права сайта и задач статуса), W — полный доступ.

`OrderHistory` пишет историю при сохранении; чтение: `CSaleOrderChange::GetList()` (`'@TYPE' => ['ORDER_STATUS_CHANGED', …]`).

## События

Регистрируй через `EventManager` в `init.php` либо установщике (`bitrix-events`).

| Событие | Когда / назначение |
| --- | --- |
| `OnSaleOrderBeforeSaved` | До сохранения; изменение или запрет |
| `OnSaleOrderSaved` | После сохранения (`IS_NEW`, `IS_CHANGED`); результат игнорируется |
| `OnSaleStatusOrderChange` (`VALUE`/`OLD_VALUE`), `OnSaleOrderPaid`, `OnSaleOrderCanceled`, `OnSaleStatusShipmentChange`, `OnShipmentDeducted`, `OnShipmentAllowDelivery`, `OnShipmentTrackingNumberChange` | Отложенно после сохранения |
| `OnSaleShipmentDelivery` | Разрешение доставки обработано, `Order::save()` ещё не завершён |
| `OnBefore/On/OnAfter` + `Sale{BasketItem,Payment,Shipment,ShipmentItem,PropertyValue}SetField`, `OnSale*EntitySaved` | Для каждой сущности |
| `OnSaleBasketItemBeforeSaved`, `OnSaleBasketItemSaved`, `OnSaleBasketItemRefreshData` | Корзина |
| `OnBeforeSaleOrderFinalAction`, `OnAfterSaleOrderFinalAction` | До / после `doFinalAction()` |
| `OnSaleOrderBeforeArchived` | До архивации |

- **Не вызывай `$order->save()` из `OnSaleOrderSaved`**: рекурсия. Меняй в `OnSaleOrderBeforeSaved` либо ставь фоновую задачу, перечитывающую заказ (`bitrix-background-jobs`).
- `EventResult::ERROR` из `OnBefore*` превращается в ошибку `setField()`/`save()`.
