# Ограничения, несовместимость и соответствие REST

## Ограничения исполнения

- `Chat::sendMessage()` обходит проверку прав, как и `CIMMessenger::Add()` с `SKIP_USER_CHECK => 'Y'`. Для пользовательской отправки сначала проверяй `canDo(Action::Send)`.
- `sendMessage()` откладывает push, счётчики, `OnAfterMessagesAdd`, ботов и команды через `Application::addBackgroundJob()`. CLI, не дошедший до `Application::terminate()`, потеряет эту работу без `$result->getPromise()?->wait()`.
- Без `AUTHOR_ID` в агенте автором группы станет пользователь контекста 0; это журналируется как устаревший вариант.
- `NotifyChat::sendMessage()` — заглушка; используй `CIMNotify::Add()`.
- Текстовая Closure в коробке получает язык запроса, а не получателя; `CIMNotify::getTextMessageByLang()` использует язык получателя только с модулем `bitrix24`.
- У подтверждения без `NOTIFY_TAG` событие `OnBeforeConfirmNotify` не сработает.

## Несовместимые изменения

| Since | Изменение | Действие |
| --- | --- | --- |
| im 26.700 | REST `im.v2.Guest.joinByCode` удалён | используй `im.v2.Guest.checkSession` (`checkSessionAction(string $code, ?Token $token = null)`) | <!-- verify:negative -->
| im 26.700 | установщик больше не добавляет правило `/guest/{code}` в urlrewrite | сохрани свой маршрут, если он нужен |
| im 26.800 | REST `im.v2.Chat.readByType` удалён | используй `im.v2.Chat.readByRecentSection` (`readByRecentSectionAction(…, string $recentSection, ?int $parentId = null)`) | <!-- verify:negative -->
| im 26.900 | 10 классов `Bitrix\Im\V2\Chat\ExternalChat\Event\*` принимают `int $initiatorId` вторым аргументом | исправь создание событий; чтение: `InitiatedByUserInterface::getInitiatorId()`; в 26.800 — `$event->getChat()->getContext()->getUserId()` |
| im 26.900 | `isChatFoldersAvailable()` заменён `Features::isChatFoldersWebAvailable()` и опцией `chat_folders_web_available`; старый ключ игнорируется. `isCopilotSelectModelAvailable()`, `isCopilotReasoningAvailable()`, `isUnreadRecentModeAvailable()` удалены | обнови вызовы/опции; не создавай `Features` позиционными аргументами | <!-- verify:negative -->
| im 26.900 | удалена `BlocksBuilder\BuilderError::BLOCK_NOT_AVAILABLE` | убери проверку: блоки включены всегда | <!-- verify:negative -->
| im 26.900 | установка через `UpdateSystem\Migration`, каталог `install/migrations/`, SQL-файлы удалены. `UnInstallDB()` удаляет таблицы, если `savedata !== 'Y'` | ОПАСНО: при сохранении данных в скрипте переустановки передавай именно строку `'Y'` |

**Since im 26.1000** REST V2 удаления и изменения сообщений возвращает объект с `result.result: true`, а не один `bool`. Проверяй вложенный признак успеха; для старой версии разбирай её документированный ответ. Это не меняет возвращаемый PHP-тип обёрток `CIM*`.

## Соответствие REST и PHP

| REST приложения/облака | PHP в `/local` |
| --- | --- |
| `im.message.add` | `CIMChat::AddMessage()` / `CIMMessage::Add()` / `Chat::sendMessage()` |
| `im.chat.add` | `ChatFactory::getInstance()->addChat()` / `addUniqueChat()` |
| `im.notify.system.add`, `im.notify.personal.add` | `CIMNotify::Add()` с `IM_NOTIFY_SYSTEM` / `IM_NOTIFY_FROM` |
| `im.notify.delete` (by `TAG`, `SUB_TAG`) | `CIMNotify::DeleteByTag()`, `DeleteBySubTag()` |
| `imbot.v2.Bot.register` | `Bot::register()` для модульного бота; REST-бот использует `RestBot` |
| `imbot.v2.Chat.Message.send` | `Bot::addMessage()` |
| `imbot.v2.Command.register` | `Command::register()` |
