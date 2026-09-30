---
name: bitrix-pull
description: 'Pull module realtime events — Bitrix\Pull\Event::add, CPullWatch tags, BX.PULL.subscribe callbacks, mobile push via Push::add. Use for live UI updates, counters, notifications or collaborative screens driven from PHP.'
---

# Realtime Pull (`pull`)

Baseline: main 23.0+ · Verified: pull 26.100.0, main 26.800.0

`pull` delivers short commands to browsers and the mobile app through the push server. Needs a configured server (`CPullOptions::GetQueueServerStatus()`); mobile push also `CPullOptions::GetPushStatus()`.

| Mechanism | Use for |
| --- | --- |
| Pull | Instant UI signal: "row 42 changed", counters |
| Messenger / agents (`bitrix-background-jobs`) | Durable work with retries; emit Pull from the worker when the UI must refresh |

## Send from PHP

```php
\Bitrix\Main\Loader::includeModule('pull');

$ok = \Bitrix\Pull\Event::add([$userId, $otherUserId], [
    'module_id' => 'vendor.module',   // lower-cased by the kernel
    'command' => 'itemUpdated',
    'params' => ['id' => $itemId],   // IDs and flags only; load data via AJAX afterwards
    // 'expiry' => 86400,            // seconds, default
]);
if (!$ok) {
    $error = \Bitrix\Pull\Event::getLastError();
}
```

- Recipients: user ID(s), 32-char channel IDs or `Bitrix\Pull\Model\Channel`; 3rd arg `\CPullChannel::TYPE_PRIVATE` (default) / `TYPE_SHARED`.
- Per-user params: `'user_params' => [$userId => ['unread' => 3]]` (merged into `params` for that user), `'dictionary' => [...]` (merged for all). Result is reliable **since pull 26.100**; before it a falsy result for these keys is not a failure — check `getLastError()`.
- Invalid UTF-8 anywhere in the payload → `false` (`EVENT_BAD_ENCODING`).
- Delivery is queued and sent by a background job at the end of the hit. Long-running CLI worker: call `\Bitrix\Pull\Event::send()` right after `add()`.

## Tags (`CPullWatch`)

```php
\CPullWatch::Add($userId, 'VENDOR_ITEM_' . $itemId);          // when rendering the page
\CPullWatch::AddToStack('VENDOR_ITEM_' . $itemId, [           // later, to all watchers
    'module_id' => 'vendor.module',
    'command' => 'itemUpdated',
    'params' => ['id' => $itemId],
]);
```

- `Add()` is written at `OnAfterEpilog` for the **current** user; for another user or outside a web hit pass 3rd arg `true` (immediate).
- A watch lives ~30 min; the page keeps it alive with `BX.PULL.extendWatch(tag)`.
- `AddToStack()` accepts an array of tags and `skip_users`.

## Subscribe in JS

Add `pull.client` to `rel` (`import { PULL } from 'pull.client'`, global `BX.PULL`). `moduleId` must be lower-case.

```javascript
// With command: (params, extra, command, info)
const off = BX.PULL.subscribe({
    moduleId: 'vendor.module',
    command: 'itemUpdated',
    callback: (params, extra, command) => reload(params.id),
});

// Without command: one object for every module command
BX.PULL.subscribe({
    moduleId: 'vendor.module',
    callback: (data) => { /* data.command, data.params, data.extra */ },
});

BX.PULL.extendWatch('VENDOR_ITEM_' + itemId);
off(); // unsubscribe
```

- Handler object: `BX.PULL.attachCommandHandler({ getModuleId: () => 'vendor.module', handleItemUpdated(params, extra) {} })` — method `handle` + command with capital first letter, or `getMap()`.
- Vue: `this.$Bitrix.PullClient.get().subscribe(...)` in `mounted`, call the returned function in `unmounted` (`bitrix-vue`).
- Never use pull's global `protobuf`: since pull 26.100 it is a minimal build (`load`, `parse`, `Root`, `Type` gone). Bundle your own protobufjs without assigning `globalThis.protobuf` — pull reads `globalThis.protobuf.roots['push-server']`.

## Mobile push

`\Bitrix\Pull\Push::add($users, ['module_id' => 'vendor.module', 'push' => ['message' => 'Text', 'params' => [...]]])` — kernel entry point for push (drops `command`/`params`); or add `push` / `pushParamsCallback` to `Event::add()` to send both. `CPullStack` is legacy.

Badge refresh: `Push::add($userId, ['module_id' => 'pull', 'push' => ['badge' => 'Y']])`. `CPushManager::sendBadges()` was removed in pull 26.0 (fatal) and `MobileCounter::send()` is a no-op; the `Push::add()` call works on all versions.

## Checklist

- [ ] `module_id` / `command` identical (lower-case module) in PHP and JS.
- [ ] Payload small, UTF-8; heavy data fetched on demand.
- [ ] Tags: `CPullWatch::Add` on render + `extendWatch` in JS.
- [ ] Subscriptions removed on unmount / destroy.
- [ ] Pull never used as a job queue.
