# Сообщения и чаты

## Два API одного механизма

| API | Результат | Проверка прав | Работа после отправки |
| --- | --- | --- | --- |
| `\CIMChat::AddMessage()` (группа), `\CIMMessage::Add()` (личное), `\CIMMessenger::Add()` (любой `DIALOG_ID`) | `int\|false`; ошибка в `$APPLICATION->GetException()` | есть, кроме `SYSTEM`/`SKIP_USER_CHECK` = `Y` | ожидает; `WAIT_FULL_EXECUTION` по умолчанию `Y` |
| `Chat::getInstance($id)->withContextUser($uid)->sendMessage(Message, ?SendingConfig)` | `SendResult`: `isSuccess()`, `getMessageId()`, `getPromise()` | нет; сначала `canDo(Action::Send)` | фоновая задача |

Оба пути приходят в `Bitrix\Im\V2\Chat::sendMessage()`. Собственный REST ядра `im.v2.Chat.Message.send` использует `CIMMessenger::Add()`.

```php
<?php

declare(strict_types=1);

namespace Vendor\Project\Integration\Im;

use Bitrix\Im\V2\Chat;
use Bitrix\Im\V2\Chat\ChatFactory;
use Bitrix\Im\V2\Message;
use Bitrix\Im\V2\Permission\Action;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;

final class OrderChat
{
    public function __construct()
    {
        Loader::requireModule('im');
    }

    /** One chat per order; returns the chat id. */
    public function ensure(int $orderId, int $ownerId, array $userIds): Result
    {
        $add = ChatFactory::getInstance()->addUniqueChat([
            'TYPE' => Chat::IM_TYPE_CHAT,
            'ENTITY_TYPE' => 'VENDOR_ORDER',
            'ENTITY_ID' => (string)$orderId,
            'TITLE' => 'Заказ №' . $orderId,
            'AUTHOR_ID' => $ownerId,          // обязателен; 0 журналируется как устаревший вариант
            'USERS' => $userIds,
            'SKIP_ADD_MESSAGE' => 'Y',        // без приветствия и строк о добавлении пользователей
        ]);

        return $add->isSuccess() ? (new Result())->setData(['chatId' => $add->getChatId()]) : $add;
    }

    public function post(int $chatId, int $authorId, string $bbText): Result
    {
        $chat = Chat::getInstance($chatId)->withContextUser($authorId);  // NullChat, если чата нет
        if (!$chat->canDo(Action::Send)) {
            return (new Result())->addError(new Error('Нет права записи в чат', 'ACCESS_DENIED'));
        }
        $send = $chat->sendMessage((new Message())->setMessage($bbText)->setAuthorId($authorId));
        $send->getPromise()?->wait();   // в CLI/агенте завершить push, счётчики и события сейчас

        return $send;
    }

    public function systemLine(int $chatId, string $text): int|false
    {
        return \CIMChat::AddMessage([
            'TO_CHAT_ID' => $chatId,
            'FROM_USER_ID' => 0,
            'SYSTEM' => 'Y',                  // без автора; обходит права и slash-команды
            'MESSAGE' => $text,
            'URL_PREVIEW' => 'N',
        ]);
    }
}
```

- `addUniqueChat()` блокирует пару `ENTITY_TYPE`+`ENTITY_ID` и возвращает существующий чат; `addChat()` всегда создаёт. Legacy `(new \CIMChat($userId))->Add($params)` вызывает `addChat()`.
- Параметры: `TYPE` (`Chat::IM_TYPE_CHAT`, `IM_TYPE_OPEN`, `IM_TYPE_CHANNEL`), `TITLE`, `DESCRIPTION`, `USERS`, `MANAGERS`, `AUTHOR_ID`, `ENTITY_TYPE`, `ENTITY_ID`, `ENTITY_DATA_1`, `AVATAR`, `COLOR`, `SKIP_ADD_MESSAGE`.
- ID диалога: `chat123` для чата, ID пользователя для личного диалога. Преобразование: `\Bitrix\Im\Dialog::getChatId($dialogId, $userId)`; личный чат: `ChatFactory::getInstance()->getPrivateChat($fromUserId, $toUserId)`.
- Участники: `$chat->addUsers([$id])`, `$chat->deleteUser($id)` возвращают `Result`; legacy: `(new \CIMChat($actorId))->AddUser($chatId, $userIds)`.
- Текст — BB-код (`[B]`, `[URL=…]`, `[USER=5][/USER]`, `[CHAT=12][/CHAT]`), до 20000 символов.

## Вложения и клавиатура

```php
$attach = new \CIMMessageParamAttach(null, '#29619b');
$attach->AddLink(['NAME' => 'Заказ №15', 'LINK' => '/shop/orders/15/']);
$attach->AddGrid([['NAME' => 'Итого', 'VALUE' => '1 500', 'DISPLAY' => 'LINE']]);
\CIMChat::AddMessage(['TO_CHAT_ID' => $chatId, 'FROM_USER_ID' => $userId, 'MESSAGE' => 'Оплачено', 'ATTACH' => $attach]);
```

V2: `(new Message())->setAttach($attach)->setKeyboard($keyboard)`. Кнопкам команд `\Bitrix\Im\Bot\Keyboard` нужен бот: `rules/bots.md`.

## События модуля `im`

| Событие | Аргументы обработчика | Поведение |
| --- | --- | --- |
| `OnBeforeMessageNotifyAdd` | `array &$fields` | любая отправка, несмотря на имя; `false` либо `['result' => false, 'reason' => '…']` отменяет её |
| `OnBeforeChatMessageAdd` | `array $messageFields, array $chatFields` | групповые чаты; отмена как выше, `['fields' => [...]]` меняет поля |
| `OnAfterMessagesAdd` | `int $messageId, array $fields` | выполняется в фоновой части |
| `OnAfterMessagesDelete` | `int $messageId, array $fields, array $flags` | |
| `OnChatUserAdd` | `Main\Event`: `CHAT_ID`, `NEW_USERS`, `CHAT`, `ENTITY_TYPE`, `ENTITY_ID` | регистрируй через `addEventHandler`, получает объект события |

Чаты сущностей других модулей (задачи, проекты) также отправляют типизированные `On<Action>ExternalChat<EntityType>`, например `OnAfterSendMessageExternalChatTasksTask`; методы `AfterSendMessageEvent::getChat()`, `getMessage()`.
