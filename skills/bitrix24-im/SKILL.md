---
name: bitrix24-im
description: 'Мессенджер в PHP коробки Битрикс24: Im\V2\Chat, сообщения, уведомления CIMNotify, модульные боты Im\Bot. Для отправки сообщений и управления чатами из /local.'
---

# Мессенджер Битрикс24: серверный PHP

Scope: Bitrix24 on-premise · Verified: im 26.1300.0, main 26.1000.0

Перед PHP-кодом один раз прочитай `bitrix-framework`.

| Задача | Открыть |
| --- | --- |
| Сообщения, вложения/клавиатура, создание чатов, участники, события | `rules/messages-chats.md` |
| Уведомления: типы, теги, удаление, подтверждение, настройки | `rules/notifications.md` |
| Модульные боты и slash-команды; REST `imbot.v2`, открытые линии | `rules/bots.md` |
| Несовместимые изменения, соответствие REST и PHP | `rules/pitfalls.md` |

## Общие правила

- Подключи `Loader::includeModule('im')` перед классами мессенджера (`Bitrix\Im\V2\Chat`, `CIMChat`, `CIMNotify` …).
- Передавай ID пользователей явно: `FROM_USER_ID`, `AUTHOR_ID`, `TO_USER_ID`, пользователь контекста. У агента/CLI нет авторизованного `$USER`.
- `Bitrix\Im\V2\Chat::sendMessage()` не проверяет права автора: сначала `canDo(Action::Send)` либо `CIMChat::AddMessage()`, который проверяет права, если `SYSTEM`/`SKIP_USER_CHECK` не равны `Y`.
- Push, счётчики, `OnAfterMessagesAdd`, боты выполняются фоном после ответа. В CLI вызывай `$result->getPromise()?->wait()`; обёртки `CIM*` ждут по умолчанию.
- Для уведомлений используй `CIMNotify::Add()`. `NotifyChat::sendMessage()` в проверенном ядре остаётся заглушкой.
- Задавай `NOTIFY_TAG`: одинаковый тег заменяет прежнее уведомление пользователя и позволяет его удалить.
- Чаты своей сущности привязывай через собственный `ENTITY_TYPE` и `addUniqueChat()`. Не записывай `b_im_*` напрямую.
