# Payments and pay system handlers

## Payments in an order

- Create: `$order->getPaymentCollection()->createItem($service)`. Several payments per order = split or partial payment.
- Available services: `PaySystem\Manager::getListWithRestrictions($payment, RestrictionManager::MODE_CLIENT|MODE_MANAGER)`, or `getListWithRestrictionsByOrder($order)` before a payment exists.
- Manual confirm: `$payment->setPaid('Y')`. Refund: `$payment->setReturn(Payment::RETURN_PS|RETURN_INNER|RETURN_NONE)`; partial: `$service->refund($payment, $sum)` (handler implements `PaySystem\IRefund`). Recurring: `PaySystem\IRecurring`, `$service->isRecurring($payment)`, `$service->repeatRecurrent($payment)`.
- Inner account: `PaySystem\Manager::getInnerPaySystemId()`, `$payment->isInner()`. Balance: legacy `CSaleUserAccount::GetByUserID($userId, $currency)`, `CSaleUserAccount::UpdateAccount($userId, $delta, $currency, …)` — **pass the delta, not the new total**. Journal: `Internals\UserTransactTable`. Buyer aggregates: `Bitrix\Sale\BuyerStatistic`.

## Start payment from a request

```php
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem\BaseServiceHandler;

// rights first: CSaleOrder::CanUserViewOrder($orderId, $groups, $userId)
$order = Order::load($orderId) ?? throw new \RuntimeException('not found');
$payment = $order->getPaymentCollection()->getItemById($paymentId);
if (!$payment || (int)$payment->getPaymentSystemId() !== $requestedPaySystemId || $payment->isPaid()) {
    throw new \RuntimeException('payment mismatch');
}
$service = $payment->getPaySystem();
$result = $service->initiatePay($payment, $request, BaseServiceHandler::STRING);
// $result->isSuccess(), getTemplate(), getPaymentUrl(), getQr()
```

**Since sale 26.500** the kernel endpoints `sale_ps_ajax.php` and `sale/applepay_gateway.php` enforce the same payment ↔ order ↔ pay system match; a frontend that mixes IDs now gets an error.

## Own handler

- Location: `/local/php_interface/include/sale_payment/<code>/` with `handler.php` (class extends `PaySystem\ServiceHandler`), `.description.php`, `template/`. Legacy `/bitrix/modules/sale/payment/` handlers are unsupported since sale **22.200.0**.
- Callback entry: `/bitrix/tools/sale_ps_result.php` → `processRequest()`. In it, re-query the payment status from the gateway (server-to-server). Match invoice ID (`PS_INVOICE_ID`), payment number, amount (minor units) and currency. Never mark paid from the callback body alone. Handle repeated notifications idempotently.
- HTTP to the gateway: `Web\HttpClient` with SSL verification on, `redirect => false`, `privateIp => false` (`bitrix-http-client`).
- Custom restrictions: extend `Services\Base\Restriction`, register on `onSalePaySystemRestrictionsClassNamesBuildList`.

## Don't subclass kernel handlers

**Since sale 26.450** `SberbankOnlineHandler` (namespace `Sale\Handlers\PaySystem`) was rewritten. Removed: `registerOrder()`, `getRegisterOrderParams()`, `getMerchantParams()`, `isCheckSumCorrect()`, `getDescriptionCode(s)Map()`, the `PAYMENT_*` / `RESPONSE_CODE_SUCCESS` constants. Calls are fatal, overrides go silently dead. Methods are typed now (`isMyResponse(Request $request, $paySystemId): bool`, `getPaymentIdFromRequest(Request $request): string`), so an untyped override is fatal at load. Callbacks accept POST+JSON only. `AlfaBankHandler` now extends `AlfabankLegacyHandler`, so `instanceof SberbankOnlineHandler` is false for it.

Write your own `ServiceHandler` instead. If you must extend it, override only the typed hooks `getOrderDescription(Payment $payment): string` and `createHttpClient(): \Bitrix\Main\Web\HttpClient`. Load the parent via `PaySystem\Manager::includeHandler('SberbankOnline')`. URL check: `SberbankOnlineHandler::isPaymentUrlValid(string $url, bool $testMode): bool`.
