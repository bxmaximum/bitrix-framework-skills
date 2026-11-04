# Заказы: API, создание, изменение, свойства

## Выбор API

| Задача | API |
| --- | --- |
| Создать / изменить корзину, заказ, оплату, отгрузку | Объектная модель `Bitrix\Sale\*`: проверки, сохранение коллекций, события, история |
| Списки, отчёты, агрегаты | `Bitrix\Sale\Internals\*Table` (`OrderTable`, `BasketTable`, `PaymentTable`, `ShipmentTable`), для данных заказа только чтение |
| Настройки кодом | `PersonTypeTable`, `OrderPropsTable`, `OrderPropsGroupTable`, `StatusTable` + `StatusLangTable`; запись разрешена в миграциях |
| Выбор настроенной службы | `PaySystem\Manager`, `Delivery\Services\Manager`, `Cashbox\Manager`, `Services\Company\Manager`, `DiscountCouponsManager` |
| Полной замены D7 нет | `CSaleOrder::CanUserViewOrder()` и др. (права), `CSaleOrderChange` (чтение истории), `CSaleDiscount::Add/Update` (правила), `CSaleOrderUserProps` (профили), `CSaleUserAccount` (баланс), `CSaleOrderTax` (налоги) |

Не меняй заказ через `OrderTable::update()`, не создавай оплаты / отгрузки прямой вставкой. Не смешивай запись `CSale*` с загруженным в память `Order`.

## Порядок создания

Корзина → заказ → тип плательщика → привязка корзины → свойства → отгрузка → расчёт доставки → оплата → `doFinalAction(true)` → синхронизация `SUM` оплаты → повторная проверка ограничений → `save()`.

```php
<?php declare(strict_types=1);

use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem\Manager as PaySystemManager;
use Bitrix\Sale\Services\Base\RestrictionManager;

$order = Order::create($siteId, $userId); // валюта сайта, иначе базовая
$order->setPersonTypeId($personTypeId); // ДО getPropertyCollection(); привязка к сайту не проверяется
$order->setBasket($orderableBasket); // только новый заказ; rules/basket.md

$order->getPropertyCollection()->getItemByOrderPropertyCode('PHONE')?->setValue($phone); // setValue возвращает Result

$shipment = $order->getShipmentCollection()->createItem(); // системная уже есть; ей службу не назначать
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

$order->doFinalAction(true); // скидки, налоги, итоги; проверь Result
$payment->setField('SUM', $order->getPrice()); // после расчёта; повторно проверь оба списка с ограничениями

$saveResult = $order->save(); // isSuccess() И getWarningMessages()
$orderId = $saveResult->getId();
```

- Оплата и пользовательская отгрузка необязательны при первом сохранении (цифровой товар, отложенный сценарий); добавь позже в загруженный заказ.
- Интеграции: свой ключ идемпотентности; уникальность `XML_ID` не обеспечивается.
- Загрузка: `Order::load($id)`, `Order::loadByAccountNumber($number)`, `Order::loadByFilter([...])`. Блокировка редактирования: `Order::lock($id)`, `Order::isLocked($id)`, `Order::unlock($id)`.

## Изменение

Один загруженный объект, одно сохранение. Что повторить после изменения:

| Изменение | `calculateDelivery()` | `doFinalAction(true)` | Синхронизация `SUM` неоплаченной оплаты |
| --- | --- | --- | --- |
| Статус, отмена, проблемность, комментарий, трек, разрешение доставки | – | – | – |
| Местоположение / адрес ограничений | Да | Да | Если изменилась цена |
| Позиции / количество, служба / стоимость доставки, удаление отгрузки | Да | Да | Если изменилась цена |
| Купон, скидка, налог | Если влияет на доставку | Да | Если изменилась цена |

- Уменьшение количества: сначала `ShipmentItem::setQuantity()`, затем `BasketItem::setField('QUANTITY')`. Увеличение: сначала корзина, затем отгрузка. Обнови позицию, пересчитай, сохрани.
- Отмена: `setField('CANCELED', 'Y')` + `REASON_CANCELED`. Запрещена при оплаченной оплате или отгруженной отгрузке. `Order::delete()` — служебное физическое удаление, не отказ покупателя.
- Смена `PERSON_TYPE_ID` — миграция: значения свойств не переносятся. `CURRENCY`/`USER_ID` через `setField()` не меняются. Не записывай `SUM_PAID`/`PAYED` напрямую.

## Свойства

Настройка: `OrderPropsTable`, привязка к типу плательщика, `ENTITY_TYPE` ORDER/SHIPMENT. Значение заказа: `PropertyValueCollection`. Настройки `OrderPropsGroupTable::add()` + `OrderPropsTable::add()` создавай миграцией, не при оформлении.

- Поиск: `getItemByOrderPropertyCode()` (первое совпадение), `getItemByOrderPropertyId()`; роли `getDeliveryLocation()`, `getPhone()`, `getUserEmail()`; группы `getGroups()`.
- `LOCATION` принимает код местоположения, не название; `ENUM` — `VALUE` варианта (варианты: `$value->getPropertyObject()->getOptions()`); `MULTIPLE = Y` — массив. Форма: `$order->getPropertyCollection()->setValuesFromPost($post, $files)` + `verify()`.
- Обязательность до сохранения: у каждого значения `isRequired()` + `checkRequiredValue($key, $value)`.
- Сохранение значений только `Order::save()`, без записи `OrderPropsValueTable`.
