# Lists, archive, components, behaviour changes

## Lists and mass operations

- Lists/aggregates: `Internals\*Table` ORM with explicit `select`. Batch related tables by an `ORDER_ID` array (no N+1). Order-level flags `PAYED`/`DEDUCTED`/`CANCELED` avoid loading collections.
- Mass updates: pick IDs in chunks, then load/change/save each order. One `doFinalAction(true)` and one `save()` per logical operation.
- `Result::isSuccess()` can be true while `getWarningMessages()` hides sub-object failures: reload and verify critical state.

## Archive

- Archived orders leave the active tables. Read: `Archive\Manager::getList()`, `getById()`, `getBasketList()`, `getBasketItemById()`. `returnArchivedOrder($id)` gives a **read-only** object; never save it as active. Combine active + archive explicitly.
- Write: `Archive\Manager::archiveOrders($filter, $limit, $timeExecution)` archives **and deletes the active orders**. `archiveByOptions($limit, $timeExecution)` runs one batch with the module settings (call from your own scheduler). `archiveOnAgent()` is the system agent, don't call it. `delete($id)` also removes archived basket rows. `OnSaleOrderBeforeArchived` fires before each.

## Components, controllers

- Public checkout: `bitrix:sale.order.ajax`. Customize business logic via the object model and events, not by patching component internals.
- Own endpoints: thin Engine controllers + services reusing sale entities (`bitrix-controllers`). `sale` enables `controllers.restIntegration`.
- **Since sale 26.500** the `ajax.php` of `sale.personal.order.list`, `sale.personal.order.detail`, `sale.order.payment.change` needs POST **and** a valid `sessid`. Custom templates sending GET or no `sessid` get an empty response.

## Other behaviour changes (sale 26.500)

- Receipts (`Cashbox\Check`): VAT comes from the basket item `VAT_RATE` first (0 included), catalog VAT second. Fix VAT on basket rows, not only in the catalog.
- `admin/print.php` pre-escapes string values in `$arOrderProps`. Custom print forms in `/bitrix/admin/reports/` must not escape them again (`&amp;quot;`).
- Fresh installs create money columns (basket, payment, shipment) as `decimal(26,8)`; sites installed earlier have `decimal(18,4)`. Don't assume the precision.
