# Уведомления

`\CIMNotify::Add($fields)` возвращает ID либо `false`; это рабочий API уведомлений проверенного ядра. `NotifyChat::sendMessage()` остаётся заглушкой с пустым результатом. Ошибка: `false` и `$APPLICATION->GetException()`.

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
            'FROM_USER_ID' => 0,                     // системный отправитель
            'NOTIFY_TYPE' => IM_NOTIFY_SYSTEM,       // IM_NOTIFY_FROM: пользователь, FROM_USER_ID > 0
            'NOTIFY_MODULE' => self::MODULE_ID,      // ключ своей записи OnGetNotifySchema
            'NOTIFY_EVENT' => 'order_paid',
            'NOTIFY_TAG' => 'VENDOR_ORDERS|PAID|' . $orderId . '|' . $managerId,
            'NOTIFY_SUB_TAG' => 'VENDOR_ORDERS|' . $orderId,
            'NOTIFY_MESSAGE' => fn (?string $lang) => Loc::getMessage('VENDOR_ORDERS_PAID', ['#ID#' => $orderId], $lang),
            'NOTIFY_MESSAGE_OUT' => 'Заказ №' . $orderId . ' оплачен', // простой текст для почты и push
        ]);
    }
}
```

## Поля

| Ключ | Значение |
| --- | --- |
| `TO_USER_ID`, `FROM_USER_ID` | получатель; отправитель (`0` — система) |
| `NOTIFY_TYPE` | `IM_NOTIFY_SYSTEM` (4), `IM_NOTIFY_FROM` (2), `IM_NOTIFY_CONFIRM` (1, с кнопками) |
| `NOTIFY_MODULE`, `NOTIFY_EVENT` | источник; настройки пользователя по модулю/событию определяют показ, почту и push |
| `NOTIFY_TAG` | ключ замены: новое уведомление удаляет прежние с тем же тегом у пользователя; для `CONFIRM` — у всех пользователей |
| `NOTIFY_SUB_TAG` | дополнительный ключ массового удаления |
| `NOTIFY_MESSAGE` / `NOTIFY_MESSAGE_OUT` | BB-код / простой текст для почты и push |
| `NOTIFY_TITLE`, `NOTIFY_LINK`, `ATTACH`, `PUSH_MESSAGE` | тема письма, ссылка, вложение, текст push |
| `NOTIFY_BUTTONS` | для `CONFIRM`: `[['TITLE' => 'Принять', 'VALUE' => 'Y', 'TYPE' => 'accept'], ['TITLE' => 'Отклонить', 'VALUE' => 'N', 'TYPE' => 'cancel']]` |

- Текст и подпись кнопки принимают `\Closure` с `($languageId, $userId)`. В коробке без модуля `bitrix24` используется язык текущего запроса, не получателя. Другой callable вместо Closure даст `null` в тексте.
- Если сущность уведомляет нескольких пользователей, включай получателя в тег; иначе `DeleteByTag()` удалит уведомления у всех.

## Удаление

```php
\CIMNotify::DeleteByTag('VENDOR_ORDERS|PAID|' . $orderId . '|' . $managerId); // второй аргумент — ID автора
\CIMNotify::DeleteBySubTag('VENDOR_ORDERS|' . $orderId); // все уведомления заказа
\CIMNotify::DeleteByModule('vendor.orders', 'order_paid'); // очистка при удалении модуля
```

## Кнопки подтверждения

Нажатие отправляет `im:OnBeforeConfirmNotify($module, $tag, $value, $notify)` всем модулям, только если `NOTIFY_TAG` непустой. Проверяй модуль, извлекай ID из тега. Верни показываемую пользователю строку либо `['result' => false, 'text' => '…']` для отказа. Затем идёт `OnAfterConfirmNotify($module, $tag, $value, $notify, $resultMessages)`. Свободный ответ (`NOTIFY_ANSWER => 'Y'`): `OnAnswerNotify($module, $tag, $text, $notify)`.

## Схема пользовательских настроек

При установке зарегистрируй `im:OnGetNotifySchema` (`bitrix-events`) и возвращай:

```php
['vendor.orders' => [
    'NAME' => 'Заказы',
    'NOTIFY' => [
        'order_paid' => ['NAME' => 'Заказ оплачен', 'MAIL' => 'N', 'PUSH' => 'Y'],
    ],
]]
```

Запись появляется в настройках уведомлений пользователя: переключатели сайта, почты и push.
