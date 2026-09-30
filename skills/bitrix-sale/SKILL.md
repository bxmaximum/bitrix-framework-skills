---
name: bitrix-sale
description: 'Online store (sale) — Fuser basket, Order object model, properties, statuses, payments, pay system handlers, delivery, shipments, discounts, coupons, reservation. Use when building cart, checkout, order lifecycle or payment/delivery integrations.'
---

# Online store (`sale`)

Baseline: main 23.0+ · Verified: sale 26.500.0, main 26.800.0

`sale` owns basket, orders, payments, shipments, discounts, statuses and history. Products, prices, stock → `bitrix-catalog`.

| Task | Read |
| --- | --- |
| API level choice, create/update an order, order properties | `rules/order.md` |
| Basket, Fuser, discount preview before checkout | `rules/basket.md` |
| Statuses, rights, history, events | `rules/statuses-events.md` |
| Payments, own pay system handlers, callbacks | `rules/payments.md` |
| Delivery services, shipments, reservation, deduction | `rules/delivery-reservation.md` |
| Cart rules, coupons, applied discounts | `rules/discounts.md` |
| Lists, archive, components, behaviour changes | `rules/archive-components.md` |

## Invariants

- `Loader::includeModule('sale')` (+ `catalog` for products, prices, reservation).
- Change orders only through the object model (`Order`, its collections) and one `save()` per operation. `Internals\*Table` ORM is read-only for order data. Never `Order::load()` in a loop.
- The object API never checks rights: check `CSaleOrder::CanUserViewOrder()` and friends before acting on a user request.
- Take pay systems and delivery services from restricted lists, never a raw ID from the request.
- Check `isSuccess()` **and** `getWarningMessages()`. Never call `$order->save()` inside `OnSaleOrderSaved`.
