# Messages and chats

## Two paths, same engine

| Path | Returns | Rights check | After-send work |
| --- | --- | --- | --- |
| `\CIMChat::AddMessage()` (group), `\CIMMessage::Add()` (private), `\CIMMessenger::Add()` (any `DIALOG_ID`) | `int\|false`; error in `$APPLICATION->GetException()` | yes, unless `SYSTEM` or `SKIP_USER_CHECK` = `Y` | waits (`WAIT_FULL_EXECUTION` defaults to `Y`) |
| `Chat::getInstance($id)->withContextUser($uid)->sendMessage(Message, ?SendingConfig)` | `SendResult`: `isSuccess()`, `getMessageId()`, `getPromise()` | no, call `canDo(Action::Send)` | background job |

Both end in `Bitrix\Im\V2\Chat::sendMessage()`; the kernel's own REST `im.v2.Chat.Message.send` calls `CIMMessenger::Add()`.

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
            'TITLE' => 'Order #' . $orderId,
            'AUTHOR_ID' => $ownerId,          // required; 0 is logged as deprecated
            'USERS' => $userIds,
            'SKIP_ADD_MESSAGE' => 'Y',        // no greeting/"users added" lines
        ]);

        return $add->isSuccess() ? (new Result())->setData(['chatId' => $add->getChatId()]) : $add;
    }

    public function post(int $chatId, int $authorId, string $bbText): Result
    {
        $chat = Chat::getInstance($chatId)->withContextUser($authorId);  // NullChat if missing
        if (!$chat->canDo(Action::Send)) {
            return (new Result())->addError(new Error('Cannot write to chat', 'ACCESS_DENIED'));
        }
        $send = $chat->sendMessage((new Message())->setMessage($bbText)->setAuthorId($authorId));
        $send->getPromise()?->wait();   // only in CLI/agents: finish push, counters, events now

        return $send;
    }

    public function systemLine(int $chatId, string $text): int|false
    {
        return \CIMChat::AddMessage([
            'TO_CHAT_ID' => $chatId,
            'FROM_USER_ID' => 0,
            'SYSTEM' => 'Y',                  // no author, skips rights check and slash commands
            'MESSAGE' => $text,
            'URL_PREVIEW' => 'N',
        ]);
    }
}
```

- `addUniqueChat()` locks on `ENTITY_TYPE`+`ENTITY_ID` and returns the existing chat when present; `addChat()` always creates. Legacy `(new \CIMChat($userId))->Add($params)` wraps `addChat()`.
- Chat params: `TYPE` (`Chat::IM_TYPE_CHAT`, `IM_TYPE_OPEN`, `IM_TYPE_CHANNEL`), `TITLE`, `DESCRIPTION`, `USERS`, `MANAGERS`, `AUTHOR_ID`, `ENTITY_TYPE`, `ENTITY_ID`, `ENTITY_DATA_1`, `AVATAR`, `COLOR`, `SKIP_ADD_MESSAGE`.
- Dialog ids: `chat123` for chats, a user id for private dialogs. `\Bitrix\Im\Dialog::getChatId($dialogId, $userId)` resolves them; `ChatFactory::getInstance()->getPrivateChat($fromUserId, $toUserId)` gives the private chat.
- Members: `$chat->addUsers([$id])`, `$chat->deleteUser($id)` (returns `Result`); legacy `(new \CIMChat($actorId))->AddUser($chatId, $userIds)`.
- Text is BB-code (`[B]`, `[URL=…]`, `[USER=5][/USER]`, `[CHAT=12][/CHAT]`); max 20000 chars.

## Attach and keyboard

```php
$attach = new \CIMMessageParamAttach(null, '#29619b');
$attach->AddLink(['NAME' => 'Order #15', 'LINK' => '/shop/orders/15/']);
$attach->AddGrid([['NAME' => 'Total', 'VALUE' => '1 500', 'DISPLAY' => 'LINE']]);
\CIMChat::AddMessage(['TO_CHAT_ID' => $chatId, 'FROM_USER_ID' => $userId, 'MESSAGE' => 'Paid', 'ATTACH' => $attach]);
```

V2: `(new Message())->setAttach($attach)->setKeyboard($keyboard)`. Keyboards (`\Bitrix\Im\Bot\Keyboard`) with command buttons need a bot: `rules/bots.md`.

## Events (module `im`)

| Event | Handler arguments | Notes |
| --- | --- | --- |
| `OnBeforeMessageNotifyAdd` | `array &$fields` | every send despite the name; return `false` or `['result' => false, 'reason' => '…']` to cancel |
| `OnBeforeChatMessageAdd` | `array $messageFields, array $chatFields` | non-private chats; cancel as above; `['fields' => [...]]` modifies |
| `OnAfterMessagesAdd` | `int $messageId, array $fields` | runs in the background part |
| `OnAfterMessagesDelete` | `int $messageId, array $fields, array $flags` | |
| `OnChatUserAdd` | `Main\Event`: `CHAT_ID`, `NEW_USERS`, `CHAT`, `ENTITY_TYPE`, `ENTITY_ID` | register with `addEventHandler` (gets the event object) |

Entity chats of other modules (tasks, projects) also send typed events `On<Action>ExternalChat<EntityType>`, e.g. `OnAfterSendMessageExternalChatTasksTask` with `AfterSendMessageEvent::getChat()`, `getMessage()`.
