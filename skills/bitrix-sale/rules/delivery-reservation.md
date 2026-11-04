# Доставка, отгрузки, резерв

## Службы доставки

- Доступные отгрузке: `Delivery\Services\Manager::getRestrictedObjectsList($shipment)` либо `getRestrictedList($shipment, Restrictions\Manager::MODE_CLIENT)`. Объект службы: `getObjectById()`. ID запроса проверяй по списку с ограничениями.
- Стоимость: `ShipmentCollection::calculateDelivery()` для всех несистемных отгрузок, кроме `CUSTOM_PRICE_DELIVERY = 'Y'`; либо `Manager::calculateDeliveryPrice($shipment, $deliveryId, $extraServices)` → `CalculationResult` (цена, срок).
- Свой обработчик: наследуй `Delivery\Services\Base` (`calculateConcrete()`, `getConfigStructure()`), зарегистрируй на `onSaleDeliveryHandlersClassNamesBuildList`, создай через `Manager::add()`. Ограничения: `onSaleDeliveryRestrictionsClassNamesBuildList`. Допуслуги: `Delivery\ExtraServices\*` + `Shipment::setExtraServices()`.

## Отгрузки

- В коллекции всегда есть **системная отгрузка** (`isSystem()`) с нераспределённым количеством. Не редактируй и не назначай ей службу.
- Частичные отгрузки: распределяй количества по пользовательским, проверяй `getBasketItemDistributedQuantity()`.
- Состояние: `allowDelivery()` / `disallowDelivery()`, списание `setField('DEDUCTED', 'Y')`, `TRACKING_NUMBER`, склад самовывоза `setStoreId()`.

## Резерв и списание

- Резерв: `Shipment::tryReserve()` / `tryUnreserve()`; полнота — `isReserved()`. По складам: `BasketItem::getReserveQuantityCollection()` → `create()`, затем `setStoreId()` **до** `setQuantity()`. Завершай `Order::save()`, не меняй табличные `RESERVED*`.
- Списание: `Shipment::setField('DEDUCTED', 'Y')`. Поставщик каталога при сохранении меняет `StoreProductTable.AMOUNT`/`QUANTITY_RESERVED`. При складском учёте сначала задай склад.
- Авторезерв: `Sale\Configuration::getProductReservationCondition()` → `Reservation\Configuration\ReserveCondition::ON_CREATE|ON_PAY|ON_FULL_PAY|ON_ALLOW_DELIVERY|ON_SHIP`; срок `getProductReserveClearPeriod()`. Очистка устаревших резервов: `Helpers\ReservedProductCleaner`.
- Доступное количество: `Reservation\BasketReservationService::getAvailableCountForBasketItem()` / `getAvailableCountForOrder()`.

**Since sale 26.600** sale.order.ajax отбрасывает недоступные для изменения DELIVERY_EXTRA_SERVICES и неверные значения. Допустимы Enum с существующим ключом цены, тип Y/N с Y либо N, точные классы Quantity и Lift. Собственный наследник Quantity не проходит проверку точного класса: для выбора при оформлении используй Enum или Y/N. На старом компоненте проверяй canUserEditValue() и значения сервером самостоятельно.
