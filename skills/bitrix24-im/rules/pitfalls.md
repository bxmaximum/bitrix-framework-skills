# Pitfalls, breaking changes, REST mapping

## Runtime pitfalls

- `Chat::sendMessage()` skips rights checks; `CIMMessenger::Add()` with `SKIP_USER_CHECK => 'Y'` too. Check `canDo(Action::Send)` for user-initiated posts.
- `sendMessage()` defers push, counters, `OnAfterMessagesAdd`, bot and command handlers to `Application::addBackgroundJob()`. A CLI script that never reaches `Application::terminate()` loses them unless it calls `$result->getPromise()?->wait()`.
- Group chat without `AUTHOR_ID` in an agent: the author falls back to the context user (`0`), which is logged as deprecated.
- `NotifyChat::sendMessage()` is a stub: use `CIMNotify::Add()`.
- Notification text closures get the request language on-premise, not the recipient's (`CIMNotify::getTextMessageByLang()` uses it only with the `bitrix24` module).
- Confirm notifications without `NOTIFY_TAG` never fire `OnBeforeConfirmNotify`.

## Breaking changes (im 26.700–26.900)

| Since | Change | Do |
| --- | --- | --- |
| im 26.700 | REST `im.v2.Guest.joinByCode` removed | use `im.v2.Guest.checkSession` (action `checkSessionAction(string $code, ?Token $token = null)`) |
| im 26.700 | installer no longer writes the `/guest/{code}` urlrewrite rule | keep your own route if you relied on it |
| im 26.800 | REST `im.v2.Chat.readByType` removed | `im.v2.Chat.readByRecentSection` (`readByRecentSectionAction(…, string $recentSection, ?int $parentId = null)`) |
| im 26.900 | 10 `Bitrix\Im\V2\Chat\ExternalChat\Event\*` classes take `int $initiatorId` as 2nd constructor argument | only code that instantiates them breaks; read it via `InitiatedByUserInterface::getInitiatorId()`; on 26.800 use `$event->getChat()->getContext()->getUserId()` |
| im 26.900 | `isChatFoldersAvailable()` → `Features::isChatFoldersWebAvailable()`, option `chat_folders_web_available` (old option ignored); `isCopilotSelectModelAvailable()`, `isCopilotReasoningAvailable()`, `isUnreadRecentModeAvailable()` removed | update calls and options; never build `Features` positionally |
| im 26.900 | `BlocksBuilder\BuilderError` lost the constant `BLOCK_NOT_AVAILABLE` | drop the check (blocks are always on) |
| im 26.900 | install via `UpdateSystem\Migration` (`install/migrations/`, SQL files gone); `UnInstallDB()` drops tables unless `savedata === 'Y'` | pass exactly `'Y'` in reinstall scripts |

## REST ↔ PHP

| REST (apps, cloud) | PHP in `/local` |
| --- | --- |
| `im.message.add` | `CIMChat::AddMessage()` / `CIMMessage::Add()` / `Chat::sendMessage()` |
| `im.chat.add` | `ChatFactory::getInstance()->addChat()` / `addUniqueChat()` |
| `im.notify.system.add`, `im.notify.personal.add` | `CIMNotify::Add()` with `IM_NOTIFY_SYSTEM` / `IM_NOTIFY_FROM` |
| `im.notify.delete` (by `TAG`, `SUB_TAG`) | `CIMNotify::DeleteByTag()`, `DeleteBySubTag()` |
| `imbot.v2.Bot.register` | `Bot::register()` (module bot; REST bots go through `RestBot`) |
| `imbot.v2.Chat.Message.send` | `Bot::addMessage()` |
| `imbot.v2.Command.register` | `Command::register()` |
