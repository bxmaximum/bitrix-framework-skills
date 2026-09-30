# Notifications

`\CIMNotify::Add($fields)` (returns the id or `false`) is the notification API in im 26.900 (the kernel itself uses it; `NotifyChat::sendMessage()` is a stub returning an empty result). Errors: `false` + `$APPLICATION->GetException()`.

```php
<?php

declare(strict_types=1);

namespace Vendor\Orders\Integration\Im;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

final class OrderNotifier
{
    public const MODULE_ID = 'vendor.orders';

    public function paid(int $orderId, int $managerId): int|false
    {
        if (!Loader::includeModule('im')) {
            return false;
        }

        return \CIMNotify::Add([
            'TO_USER_ID' => $managerId,
            'FROM_USER_ID' => 0,                     // system sender
            'NOTIFY_TYPE' => IM_NOTIFY_SYSTEM,       // IM_NOTIFY_FROM: from a user (FROM_USER_ID > 0)
            'NOTIFY_MODULE' => self::MODULE_ID,      // key of your OnGetNotifySchema entry
            'NOTIFY_EVENT' => 'order_paid',
            'NOTIFY_TAG' => 'VENDOR_ORDERS|PAID|' . $orderId . '|' . $managerId,
            'NOTIFY_SUB_TAG' => 'VENDOR_ORDERS|' . $orderId,
            'NOTIFY_MESSAGE' => fn (?string $lang) => Loc::getMessage('VENDOR_ORDERS_PAID', ['#ID#' => $orderId], $lang),
            'NOTIFY_MESSAGE_OUT' => 'Order #' . $orderId . ' paid',   // plain text for e-mail and push
        ]);
    }
}
```

## Fields

| Key | Meaning |
| --- | --- |
| `TO_USER_ID`, `FROM_USER_ID` | recipient; sender (`0` = system) |
| `NOTIFY_TYPE` | `IM_NOTIFY_SYSTEM` (4), `IM_NOTIFY_FROM` (2), `IM_NOTIFY_CONFIRM` (1, with buttons) |
| `NOTIFY_MODULE`, `NOTIFY_EVENT` | source; user settings per module/event decide whether it is shown, mailed, pushed |
| `NOTIFY_TAG` | dedupe key: a new notification with the same tag deletes the older ones in that user's feed (for `CONFIRM`: in every feed) |
| `NOTIFY_SUB_TAG` | second key for bulk deletion |
| `NOTIFY_MESSAGE` / `NOTIFY_MESSAGE_OUT` | BB-code text / plain text for e-mail and push |
| `NOTIFY_TITLE`, `NOTIFY_LINK`, `ATTACH`, `PUSH_MESSAGE` | e-mail subject, link, attach, push text |
| `NOTIFY_BUTTONS` | for `CONFIRM`: `[['TITLE' => 'Approve', 'VALUE' => 'Y', 'TYPE' => 'accept'], ['TITLE' => 'Reject', 'VALUE' => 'N', 'TYPE' => 'cancel']]` |

- Text fields and button titles accept a `\Closure` receiving `($languageId, $userId)`. On-premise (no `bitrix24` module) it is called with the **current request language**, not the recipient's; a non-Closure callable yields `null` text.
- Put the recipient into the tag when the same entity notifies several users, otherwise `DeleteByTag()` removes all of them.

## Delete

```php
\CIMNotify::DeleteByTag('VENDOR_ORDERS|PAID|' . $orderId . '|' . $managerId);   // optional 2nd arg: author id
\CIMNotify::DeleteBySubTag('VENDOR_ORDERS|' . $orderId);                        // all notifications of the order
\CIMNotify::DeleteByModule('vendor.orders', 'order_paid');                       // uninstall cleanup
```

## Confirm buttons

The click fires `im:OnBeforeConfirmNotify($module, $tag, $value, $notify)` for **every** module, and only if `NOTIFY_TAG` is not empty. Filter by module, parse the entity id from the tag, return a string shown to the user or `['result' => false, 'text' => '…']` to reject. `OnAfterConfirmNotify($module, $tag, $value, $notify, $resultMessages)` follows. Free-text answers (`NOTIFY_ANSWER => 'Y'`): `OnAnswerNotify($module, $tag, $text, $notify)`.

## User settings schema

Register `im:OnGetNotifySchema` (install step, `bitrix-events`) and return:

```php
['vendor.orders' => [
    'NAME' => 'Orders',
    'NOTIFY' => [
        'order_paid' => ['NAME' => 'Order paid', 'MAIL' => 'N', 'PUSH' => 'Y'],
    ],
]]
```

The entry lists the event in the users' notification settings (site, e-mail, push switches).
