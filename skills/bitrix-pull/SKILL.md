---
name: bitrix-pull
description: 'События Pull и мобильные push Битрикс: Pull\Event::add, теги CPullWatch, BX.PULL.subscribe. Используй для обновления интерфейса, счётчиков и уведомлений из PHP.'
---

# События реального времени (`pull`)

Baseline: main 23.0+ · Verified: pull 26.100.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

`pull` доставляет короткие команды браузерам и мобильному приложению через настроенный push-сервер (`CPullOptions::GetQueueServerStatus()`). Для мобильных push нужен также `CPullOptions::GetPushStatus()`.

| Механизм | Назначение |
| --- | --- |
| Pull | Мгновенный сигнал интерфейсу: «строка 42 изменилась», счётчики |
| Messenger / агенты (`bitrix-background-jobs`) | Надёжная обработка с повторами; после работы обработчик отправляет Pull для обновления интерфейса |

## Отправка из PHP

```php
\Bitrix\Main\Loader::includeModule('pull');

$ok = \Bitrix\Pull\Event::add([$userId, $otherUserId], [
    'module_id' => 'vendor.module',   // ядро приводит к нижнему регистру
    'command' => 'itemUpdated',
    'params' => ['id' => $itemId],   // только ID и флаги; данные затем загрузить через AJAX
    // 'expiry' => 86400,            // секунды, значение по умолчанию
]);
if (!$ok) {
    $error = \Bitrix\Pull\Event::getLastError();
}
```

- Получатели: ID пользователей, 32-символьные ID каналов или `Bitrix\Pull\Model\Channel`; третий аргумент — `\CPullChannel::TYPE_PRIVATE` (по умолчанию) / `TYPE_SHARED`.
- Персональные параметры: `'user_params' => [$userId => ['unread' => 3]]` добавляются в `params` пользователя; `'dictionary' => [...]` добавляется для всех. Результат достоверен **Since pull 26.100**; раньше ложное значение при этих ключах не обязательно означало сбой — проверяй `getLastError()`.
- Некорректный UTF-8 в любой части данных возвращает `false` (`EVENT_BAD_ENCODING`).
- Доставка ставится в очередь и выполняется фоновой задачей в конце хита. В долгом CLI-обработчике вызывай `\Bitrix\Pull\Event::send()` сразу после `add()`.

## Теги (`CPullWatch`)

```php
\CPullWatch::Add($userId, 'VENDOR_ITEM_' . $itemId);          // при выводе страницы
\CPullWatch::AddToStack('VENDOR_ITEM_' . $itemId, [           // позже: всем наблюдателям
    'module_id' => 'vendor.module',
    'command' => 'itemUpdated',
    'params' => ['id' => $itemId],
]);
```

- `Add()` записывает подписку на `OnAfterEpilog` для **текущего** пользователя. Для другого пользователя или вне веб-хита передай третий аргумент `true` (немедленная запись).
- Подписка живёт около 30 минут; страница продлевает её через `BX.PULL.extendWatch(tag)`.
- `AddToStack()` принимает массив тегов и `skip_users`.

## Подписка в JS

Добавь `pull.client` в `rel` (`import { PULL } from 'pull.client'`, глобальный `BX.PULL`). `moduleId` — в нижнем регистре.

```javascript
// С command: (params, extra, command, info)
const off = BX.PULL.subscribe({
    moduleId: 'vendor.module',
    command: 'itemUpdated',
    callback: (params, extra, command) => reload(params.id),
});

// Без command: один объект на любую команду модуля
BX.PULL.subscribe({
    moduleId: 'vendor.module',
    callback: (data) => { /* data.command, data.params, data.extra */ },
});

BX.PULL.extendWatch('VENDOR_ITEM_' + itemId);
off(); // отписаться
```

- Объект-обработчик: `BX.PULL.attachCommandHandler({ getModuleId: () => 'vendor.module', handleItemUpdated(params, extra) {} })`; метод `handle` + команда с заглавной первой буквой либо `getMap()`.
- Vue: `this.$Bitrix.PullClient.get().subscribe(...)` в `mounted`; вызови возвращённую функцию в `unmounted` (`bitrix-vue`).
- Не используй глобальный `protobuf` модуля pull: **Since pull 26.100** это минимальная сборка без `load`, `parse`, `Root`, `Type`. Собирай свой protobufjs, не присваивая `globalThis.protobuf`: pull читает `globalThis.protobuf.roots['push-server']`.

## Мобильные push

`\Bitrix\Pull\Push::add($users, ['module_id' => 'vendor.module', 'push' => ['message' => 'Текст', 'params' => [...]]])` — точка входа ядра для push (отбрасывает `command`/`params`). Для совместной отправки добавь `push` / `pushParamsCallback` в `Event::add()`. `CPullStack` устарел.

Обновление значка: `Push::add($userId, ['module_id' => 'pull', 'push' => ['badge' => 'Y']])`. `CPushManager::sendBadges()` удалён в pull 26.0 (фатальная ошибка), `MobileCounter::send()` ничего не делает. Вызов `Push::add()` работает и на старых версиях. <!-- verify:negative -->

## Проверка

- [ ] `module_id` / `command` совпадают в PHP и JS; модуль в нижнем регистре.
- [ ] Данные короткие, UTF-8; объёмные данные загружаются по требованию.
- [ ] Теги: `CPullWatch::Add` при выводе, `extendWatch` в JS.
- [ ] Подписки сняты при размонтировании / уничтожении.
- [ ] Pull не используется как очередь задач.
