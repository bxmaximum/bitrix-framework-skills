# Delivery, shipments, reservation

## Delivery services

- Available for a shipment: `Delivery\Services\Manager::getRestrictedObjectsList($shipment)` or `getRestrictedList($shipment, Restrictions\Manager::MODE_CLIENT)`. One service object: `getObjectById()`. Never trust a raw request ID without the restricted list.
- Cost: `ShipmentCollection::calculateDelivery()` (all non-system shipments; skips `CUSTOM_PRICE_DELIVERY = 'Y'`) or `Manager::calculateDeliveryPrice($shipment, $deliveryId, $extraServices)` → `CalculationResult` (price, period).
- Custom handler: extend `Delivery\Services\Base` (`calculateConcrete()`, `getConfigStructure()`), register on `onSaleDeliveryHandlersClassNamesBuildList`, create via `Manager::add()`. Restrictions: `onSaleDeliveryRestrictionsClassNamesBuildList`. Extra services: `Delivery\ExtraServices\*` + `Shipment::setExtraServices()`.

## Shipments

- The collection always holds a **system shipment** (`isSystem()`) with the undistributed quantity. Never assign it a service or edit it.
- Partial/split shipments: distribute quantities across user shipments; guard with `getBasketItemDistributedQuantity()`.
- State: `allowDelivery()` / `disallowDelivery()`, deduct via `setField('DEDUCTED', 'Y')`, `TRACKING_NUMBER`, `setStoreId()` for pickup.

## Reservation and deduction

- Reserve: `Shipment::tryReserve()` / `tryUnreserve()`; full-reserve check `isReserved()`. Per-store rows: `BasketItem::getReserveQuantityCollection()` → `create()`, then `setStoreId()` **before** `setQuantity()`. Always finish with `Order::save()`; never edit `RESERVED*` table fields.
- Deduct (write-off) = `Shipment::setField('DEDUCTED', 'Y')`. The catalog provider updates stock (`StoreProductTable.AMOUNT`/`QUANTITY_RESERVED`) on save. With inventory management on, set the store first.
- Auto-reserve config: `Sale\Configuration::getProductReservationCondition()` → `Reservation\Configuration\ReserveCondition::ON_CREATE|ON_PAY|ON_FULL_PAY|ON_ALLOW_DELIVERY|ON_SHIP`; TTL `getProductReserveClearPeriod()`. Stale reserves are cleaned by `Helpers\ReservedProductCleaner`.
- Available quantity: `Reservation\BasketReservationService::getAvailableCountForBasketItem()` / `getAvailableCountForOrder()`.
