# Оплаты и обработчики платёжных систем

## Оплаты заказа

- Создание: `$order->getPaymentCollection()->createItem($service)`. Несколько оплат — разделённый / частичный платёж.
- Доступные службы: `PaySystem\Manager::getListWithRestrictions($payment, RestrictionManager::MODE_CLIENT|MODE_MANAGER)` либо `getListWithRestrictionsByOrder($order)` до создания оплаты.
- Ручное подтверждение: `$payment->setPaid('Y')`. Возврат: `$payment->setReturn(Payment::RETURN_PS|RETURN_INNER|RETURN_NONE)`; частичный — `$service->refund($payment, $sum)`, обработчик реализует `PaySystem\IRefund`. Повторный платёж: `PaySystem\IRecurring`, `$service->isRecurring($payment)`, `$service->repeatRecurrent($payment)`.
- Внутренний счёт: `PaySystem\Manager::getInnerPaySystemId()`, `$payment->isInner()`. Баланс: `CSaleUserAccount::GetByUserID($userId, $currency)`, `CSaleUserAccount::UpdateAccount($userId, $delta, $currency, …)` — **передавай изменение, не новый итог**. Журнал: `Internals\UserTransactTable`; агрегаты покупателя: `Bitrix\Sale\BuyerStatistic`.

## Запуск оплаты по запросу

```php
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem\BaseServiceHandler;

// сначала права: CSaleOrder::CanUserViewOrder($orderId, $groups, $userId)
$order = Order::load($orderId) ?? throw new \RuntimeException('not found');
$payment = $order->getPaymentCollection()->getItemById($paymentId);
if (!$payment || (int)$payment->getPaymentSystemId() !== $requestedPaySystemId || $payment->isPaid()) {
    throw new \RuntimeException('payment mismatch');
}
$service = $payment->getPaySystem();
$result = $service->initiatePay($payment, $request, BaseServiceHandler::STRING);
// $result->isSuccess(), getTemplate(), getPaymentUrl(), getQr()
```

**Since sale 26.500** точки входа `sale_ps_ajax.php`, `sale/applepay_gateway.php` проверяют соответствие оплата ↔ заказ ↔ платёжная система. Смешанные ID дают ошибку.

## Собственный обработчик

- `/local/php_interface/include/sale_payment/<code>/`: `handler.php` с наследником `PaySystem\ServiceHandler`, `.description.php`, `template/`. Старые `/bitrix/modules/sale/payment/` не поддерживаются **Since sale 22.200.0**.
- Уведомление: `/bitrix/tools/sale_ps_result.php` → `processRequest()`. Повторно запроси статус у шлюза серверным HTTP. Сверь `PS_INVOICE_ID`, номер оплаты, сумму в минимальных денежных единицах, валюту. Не подтверждай только по телу уведомления. Повторные уведомления обрабатывай идемпотентно.
- HTTP шлюза: `Web\HttpClient` с проверкой SSL, `redirect => false`, `privateIp => false` (`bitrix-http-client`).
- Свои ограничения: наследник `Services\Base\Restriction`, регистрация на `onSalePaySystemRestrictionsClassNamesBuildList`.

## Не наследуй обработчики ядра

**Since sale 26.450** переписан `SberbankOnlineHandler` (`Sale\Handlers\PaySystem`). Удалены `registerOrder()`, `getRegisterOrderParams()`, `getMerchantParams()`, `isCheckSumCorrect()`, `getDescriptionCode(s)Map()`, константы `PAYMENT_*` / `RESPONSE_CODE_SUCCESS`. Вызовы фатальны, переопределения молча перестают работать. Методы типизированы: `isMyResponse(Request $request, $paySystemId): bool`, `getPaymentIdFromRequest(Request $request): string`; нетипизированное переопределение фатально при загрузке. Уведомления принимают только POST+JSON. `AlfaBankHandler` наследует `AlfabankLegacyHandler`, поэтому для него `instanceof SberbankOnlineHandler` ложно.

Пиши свой `ServiceHandler`. При необходимом наследовании переопределяй только типизированные `getOrderDescription(Payment $payment): string`, `createHttpClient(): \Bitrix\Main\Web\HttpClient`. Загрузи родителя через `PaySystem\Manager::includeHandler('SberbankOnline')`. Проверка URL: `SberbankOnlineHandler::isPaymentUrlValid(string $url, bool $testMode): bool`.

## Ограничения запуска — Since sale 26.600

sale.entity.initiatepay проверяет доступность платёжной системы в режиме покупателя. Для собственного менеджерского действия `Controller\Action\Entity\InitiatePayAction::setRestrictionMode()` принимает только `Services\PaySystem\Restrictions\Manager::MODE_CLIENT` / MODE_MANAGER. Менеджерский режим выбирает сервер после проверки прав, а не параметр клиента. На старом sale отфильтруй платёжную систему через `getListWithRestrictions()` до запуска.
