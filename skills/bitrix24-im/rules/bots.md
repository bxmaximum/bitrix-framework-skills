# Chat bots and slash commands

| Where the bot lives | API |
| --- | --- |
| PHP module on the box (`/local/modules/vendor.bot`) | `\Bitrix\Im\Bot::register()` + static handler methods; runs in-process, no HTTP |
| External service or cloud Bitrix24 | REST `imbot.v2.*` (webhook or `FETCH` polling via `imbot.v2.Event.get`); legacy `imbot.*` is marked outdated in the REST docs, see `bitrix24-rest-api` |

## Module bot

```php
<?php

declare(strict_types=1);

namespace Vendor\Bot;

use Bitrix\Im\Bot;
use Bitrix\Im\Bot\Keyboard;
use Bitrix\Im\Command;
use Bitrix\Main\Config\Option;

final class ReportBot
{
    public const MODULE_ID = 'vendor.bot';   // installed module: handlers run after Loader::includeModule(MODULE_ID)

    public static function install(): void   // from DoInstall(); idempotent per MODULE_ID + CODE
    {
        $botId = Bot::register([
            'CODE' => 'report',
            'TYPE' => Bot::TYPE_BOT,
            'MODULE_ID' => self::MODULE_ID,
            'CLASS' => self::class,
            'METHOD_MESSAGE_ADD' => 'onMessageAdd',
            'METHOD_WELCOME_MESSAGE' => 'onChatStart',   // or TEXT_PRIVATE_WELCOME_MESSAGE
            'METHOD_BOT_DELETE' => 'onBotDelete',
            'PROPERTIES' => ['NAME' => 'Report bot', 'WORK_POSITION' => 'Builds reports'],
        ]);
        if (!$botId) {
            return;
        }
        Option::set(self::MODULE_ID, 'bot_id', (string)$botId);

        Command::register([
            'MODULE_ID' => self::MODULE_ID,
            'BOT_ID' => $botId,
            'COMMAND' => 'report',
            'CLASS' => self::class,
            'METHOD_COMMAND_ADD' => 'onCommand',
            'METHOD_LANG_GET' => 'onCommandLang',        // without it the command is hidden
        ]);
    }

    public static function uninstall(): void
    {
        Bot::unRegister(['BOT_ID' => (int)Option::get(self::MODULE_ID, 'bot_id'), 'MODULE_ID' => self::MODULE_ID]);
    }

    public static function onMessageAdd($messageId, array $fields): bool
    {
        // BOT_ID, DIALOG_ID, FROM_USER_ID, MESSAGE_TYPE, CHAT_ENTITY_TYPE, MESSAGE (mentions stripped in chats)
        $keyboard = new Keyboard((int)$fields['BOT_ID']);
        $keyboard->addButton(['TEXT' => 'Today', 'COMMAND' => 'report', 'COMMAND_PARAMS' => 'today']);
        Bot::addMessage(['BOT_ID' => $fields['BOT_ID']], [
            'DIALOG_ID' => $fields['DIALOG_ID'],
            'MESSAGE' => 'Which period?',
            'KEYBOARD' => $keyboard,
        ]);

        return true;
    }

    public static function onChatStart($dialogId, array $joinFields): bool   // BOT_ID, USER_ID, CHAT_TYPE
    {
        return (bool)Bot::addMessage(['BOT_ID' => $joinFields['BOT_ID']], ['DIALOG_ID' => $dialogId, 'MESSAGE' => 'Hi! Send /report']);
    }

    public static function onCommand($messageId, array $fields): bool   // COMMAND, COMMAND_PARAMS, COMMAND_ID, DIALOG_ID
    {
        $botId = (int)Option::get(self::MODULE_ID, 'bot_id');
        Bot::addMessage(['BOT_ID' => $botId], ['DIALOG_ID' => $fields['DIALOG_ID'], 'MESSAGE' => 'Report: ' . $fields['COMMAND_PARAMS']]);

        return true;
    }

    public static function onCommandLang($command, $lang = null): array
    {
        return ['TITLE' => 'Build a report', 'PARAMS' => 'today|week'];
    }

    public static function onBotDelete($botId): bool
    {
        Option::delete(self::MODULE_ID, ['name' => 'bot_id']);

        return true;
    }
}
```

## Rules

- Required for a module bot: `MODULE_ID`, `CLASS`, `METHOD_MESSAGE_ADD`, `PROPERTIES.NAME` or `LAST_NAME`, and `METHOD_WELCOME_MESSAGE` or `TEXT_PRIVATE_WELCOME_MESSAGE`; otherwise `register()` returns `false`. A bot is a `b_user` row with `EXTERNAL_AUTH_ID = 'bot'`.
- Types: `TYPE_BOT` (`B`) gets private messages and chat messages only when mentioned; `TYPE_SUPERVISOR` (`S`) and `TYPE_PERSONAL` (`P`) get every message in chats they belong to; `TYPE_OPENLINE` (`O`) forces `OPENLINE = Y` for open lines. Messages from other bots are never delivered.
- `INSTALL_TYPE`: `silent` (default), `system`/`user` post an announcement to the general chat.
- Handlers are static and run in the portal request that sent the message (message handlers after the response); keep them short, enqueue heavy work (`bitrix-background-jobs`).
- `Bot::addMessage()` returns the message id or `false`; `SYSTEM => 'Y'` in a chat prefixes the bot name. `updateMessage()`, `deleteMessage()`, `startWriting()` are there too.
- Commands: `/name params` at line start, max 10 per message; ignored in system messages. `BOT_ID = 0` makes a common command; a bot command works only in the bot's dialog and chats unless `COMMON => 'Y'`; extranet users need `EXTRANET_SUPPORT => 'Y'`.
- Keyboard buttons: `COMMAND` (needs a bot), `LINK`, `ACTION` (`PUT`, `SEND`, `COPY`, `CALL`, `DIALOG`), `APP_ID`; `TEXT` is required.
- Leaving a chat has no callback: listen to `im:onImBotLeaveChat($bot, $dialogId, $fields)`. REST bots get `onImBotMessageAdd`/`onImBotV2MessageAdd`, `onImBotJoinChat`/`onImBotV2JoinChat`, `onImBotDelete`/`onImBotV2Delete`, `onImCommandAdd`/`onImBotV2CommandAdd` instead of callbacks.
- Reference implementation in the kernel: `Bitrix\ImBot\Bot\ServiceExample` (module `imbot`).

## Open lines

Open line chats have `ENTITY_TYPE = 'LINES'`; bots there need `OPENLINE = 'Y'` (or `TYPE_OPENLINE`). Sessions, operators, queues and connectors belong to `imopenlines` / `imconnector`; not covered here.
