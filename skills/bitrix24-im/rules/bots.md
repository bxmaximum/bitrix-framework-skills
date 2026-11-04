# Боты и slash-команды

| Где работает бот | API |
| --- | --- |
| PHP-модуль коробки (`/local/modules/vendor.bot`) | `\Bitrix\Im\Bot::register()` и статические обработчики; внутри PHP-запроса, без HTTP |
| Внешний сервис или облако | REST `imbot.v2.*` с доставкой событий на HTTP-обработчик либо опросом `FETCH` через `imbot.v2.Event.get`; старый `imbot.*` помечен устаревшим. Клиент: `bitrix24-rest-api` |

## Модульный бот

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
    public const MODULE_ID = 'vendor.bot';   // установленный модуль; перед обработчиком Loader::includeModule(MODULE_ID)

    public static function install(): void   // из DoInstall(); идемпотентно по MODULE_ID + CODE
    {
        $botId = Bot::register([
            'CODE' => 'report',
            'TYPE' => Bot::TYPE_BOT,
            'MODULE_ID' => self::MODULE_ID,
            'CLASS' => self::class,
            'METHOD_MESSAGE_ADD' => 'onMessageAdd',
            'METHOD_WELCOME_MESSAGE' => 'onChatStart',   // либо TEXT_PRIVATE_WELCOME_MESSAGE
            'METHOD_BOT_DELETE' => 'onBotDelete',
            'PROPERTIES' => ['NAME' => 'Бот отчётов', 'WORK_POSITION' => 'Готовит отчёты'],
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
            'METHOD_LANG_GET' => 'onCommandLang',        // без него команда скрыта
        ]);
    }

    public static function uninstall(): void
    {
        Bot::unRegister(['BOT_ID' => (int)Option::get(self::MODULE_ID, 'bot_id'), 'MODULE_ID' => self::MODULE_ID]);
    }

    public static function onMessageAdd($messageId, array $fields): bool
    {
        // BOT_ID, DIALOG_ID, FROM_USER_ID, MESSAGE_TYPE, CHAT_ENTITY_TYPE, MESSAGE (упоминания в чатах удалены)
        $keyboard = new Keyboard((int)$fields['BOT_ID']);
        $keyboard->addButton(['TEXT' => 'Сегодня', 'COMMAND' => 'report', 'COMMAND_PARAMS' => 'today']);
        Bot::addMessage(['BOT_ID' => $fields['BOT_ID']], [
            'DIALOG_ID' => $fields['DIALOG_ID'],
            'MESSAGE' => 'За какой период?',
            'KEYBOARD' => $keyboard,
        ]);

        return true;
    }

    public static function onChatStart($dialogId, array $joinFields): bool   // BOT_ID, USER_ID, CHAT_TYPE
    {
        return (bool)Bot::addMessage(['BOT_ID' => $joinFields['BOT_ID']], ['DIALOG_ID' => $dialogId, 'MESSAGE' => 'Здравствуйте! Отправьте /report']);
    }

    public static function onCommand($messageId, array $fields): bool   // COMMAND, COMMAND_PARAMS, COMMAND_ID, DIALOG_ID
    {
        $botId = (int)Option::get(self::MODULE_ID, 'bot_id');
        Bot::addMessage(['BOT_ID' => $botId], ['DIALOG_ID' => $fields['DIALOG_ID'], 'MESSAGE' => 'Отчёт: ' . $fields['COMMAND_PARAMS']]);

        return true;
    }

    public static function onCommandLang($command, $lang = null): array
    {
        return ['TITLE' => 'Сформировать отчёт', 'PARAMS' => 'today|week'];
    }

    public static function onBotDelete($botId): bool
    {
        Option::delete(self::MODULE_ID, ['name' => 'bot_id']);

        return true;
    }
}
```

## Правила

- Обязательны `MODULE_ID`, `CLASS`, `METHOD_MESSAGE_ADD`, `PROPERTIES.NAME` либо `LAST_NAME`, а также `METHOD_WELCOME_MESSAGE` либо `TEXT_PRIVATE_WELCOME_MESSAGE`. Иначе `register()` возвращает `false`. Бот хранится в `b_user` с `EXTERNAL_AUTH_ID = 'bot'`.
- `TYPE_BOT` (`B`) получает личные сообщения и сообщения чата с упоминанием; `TYPE_SUPERVISOR` (`S`) и `TYPE_PERSONAL` (`P`) — все сообщения чатов, в которых состоят. `TYPE_OPENLINE` (`O`) включает `OPENLINE = Y`. Сообщения других ботов не доставляются.
- `INSTALL_TYPE`: по умолчанию `silent`; `system`/`user` публикуют объявление в общем чате.
- Обработчики статические, выполняются в запросе отправки сообщения; обработчики сообщений — после ответа. Долгую работу ставь в очередь (`bitrix-background-jobs`).
- `Bot::addMessage()` возвращает ID либо `false`. `SYSTEM => 'Y'` в чате добавляет имя бота. Также доступны `updateMessage()`, `deleteMessage()`, `startWriting()`.
- Команды: `/name params` в начале строки, до 10 в сообщении; системные сообщения не обрабатываются. `BOT_ID = 0` создаёт общую команду; команда бота работает в его диалоге и чатах, если не задано `COMMON => 'Y'`. Для экстранета нужно `EXTRANET_SUPPORT => 'Y'`.
- Кнопки клавиатуры: `COMMAND` (нужен бот), `LINK`, `ACTION` (`PUT`, `SEND`, `COPY`, `CALL`, `DIALOG`), `APP_ID`. `TEXT` обязателен.
- Отдельного callback выхода нет: слушай `im:onImBotLeaveChat($bot, $dialogId, $fields)`. REST-бот получает события `onImBotMessageAdd`/`onImBotV2MessageAdd`, `onImBotJoinChat`/`onImBotV2JoinChat`, `onImBotDelete`/`onImBotV2Delete`, `onImCommandAdd`/`onImBotV2CommandAdd` вместо PHP-callback.
- Образец ядра: `Bitrix\ImBot\Bot\ServiceExample`, модуль `imbot`.

## Открытые линии

У чата линии `ENTITY_TYPE = 'LINES'`; боту нужен `OPENLINE = 'Y'` либо `TYPE_OPENLINE`. Сессии, операторы, очереди и коннекторы относятся к `imopenlines`/`imconnector` и требуют отдельной документации.
