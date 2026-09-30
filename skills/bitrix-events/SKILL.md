---
name: bitrix-events
description: 'Bitrix events — D7 Event/EventResult/EventManager vs legacy ExecuteModuleEventEx hooks (OnBeforeUserAdd, &$arFields), make:event, make:eventhandler, handler registration. Use when publishing, handling or cancelling module events.'
---

# Bitrix events

Baseline: main 23.0+ · Verified: main 26.800.0

## Who dispatches decides the handler signature

| Dispatcher (find it in the caller) | Examples | Handler receives | Result |
| --- | --- | --- | --- |
| `new Event(...)` + `->send()` | own events, `main:OnBeforeMailSend`, ORM tablet events | VERSION 2 (`registerEventHandler`, `addEventHandler`): the `Event` object. VERSION 1 (`*Compatible`): `...array_values($params)` | `$event->getResults()`; non-`EventResult` returns are wrapped as `UNDEFINED` |
| `GetModuleEvents()` + `ExecuteModuleEventEx()` (legacy) | `main:OnBeforeUserAdd`, `OnAfterUserAdd`, `OnPageStart`, `OnEpilog`, `iblock:OnBeforeIBlockElementAdd` | raw args, whatever VERSION was registered — e.g. `array &$arFields` | caller-specific |

- Legacy `OnBefore*`: cancel = `$APPLICATION->ThrowException('…'); return false;` (callers check `=== false`). Modify data through the reference `&$arFields`; a returned array (`['FIELDS' => …]`) is ignored.
- ORM tablet / highload record events use `Bitrix\Main\ORM\EventManager` and `ORM\EventResult::modifyFields()` — see `bitrix-orm`; HL event names — `bitrix-highloadblock`.

## Publish a D7 event

`php bitrix.php make:event PostCreated -m vendor.blog -C Post` (**Since main 25.900**; older: write the class) → `Vendor\Blog\Public\Event\Post\PostCreatedEvent` (without `-C`: `…\Public\Event\`). The generated class passes no params — add them:

```php
namespace Vendor\Blog\Public\Event\Post;

use Bitrix\Main\Event;

final class PostCreatedEvent extends Event
{
    public function __construct(public readonly int $postId, public readonly int $authorId)
    {
        parent::__construct('vendor.blog', 'PostCreated', ['postId' => $postId, 'authorId' => $authorId]);
    }
}
```

```php
$event = new PostCreatedEvent($post->getId(), $post->getAuthorId());
$event->send();
foreach ($event->getResults() as $result)
{
    if ($result->getType() === \Bitrix\Main\EventResult::ERROR)
    {
        // caller decides: abort, log, collect errors
    }
}
```

Cancel with a reason: return `new EventResult(EventResult::ERROR, ['error' => new Error('…', 'CODE')])` — only callers that read it honor it (mail events: `bitrix-mail`).

## Handle it

`php bitrix.php make:eventhandler PostCreated --event-module=vendor.blog -m vendor.notify` — `name` is the **event** name, `-m` is the handler module → `Vendor\Notify\Internals\Integration\Vendor\Blog\EventHandler\PostCreatedEventHandler::handle(PostCreatedEvent $event)`. Add the missing `use` for the event class.

```php
public static function handle(PostCreatedEvent $event): EventResult
{
    $notifier = ServiceLocator::getInstance()->get(Notifier::class);
    $result = $notifier->notifyAuthor($event->authorId);

    return new EventResult($result->isSuccess() ? EventResult::SUCCESS : EventResult::ERROR, $result->getErrorMessages());
}
```

Handlers are static callbacks, never built by the container: resolve services inside (`bitrix-framework`).

## Register

Persistent (module installer; unregister with the same args in `DoUninstall`):

```php
EventManager::getInstance()->registerEventHandler(
    fromModuleId: 'vendor.blog',
    eventType: 'PostCreated',
    toModuleId: 'vendor.notify',
    toClass: PostCreatedEventHandler::class,
    toMethod: 'handle',
);
```

- `registerEventHandlerCompatible()` (same args, VERSION 1): a D7 event then passes `...array_values($params)` instead of `Event`. For legacy `ExecuteModuleEventEx` events both calls deliver raw args; the kernel uses `*Compatible` for them by convention.
- **Since main 26.600\***: declare handlers in `install/migrations/events.php` (`register()` = VERSION 2, `registerCompatible()` = VERSION 1) — `bitrix-modules`.
- Per request only: `addEventHandler($fromModuleId, $eventType, $callback, $includeFile = false, $sort = 100)` (VERSION 2) / `addEventHandlerCompatible()` (VERSION 1), e.g. from `init.php`.
- Order: ascending `$sort` (default 100) — 5th arg of `addEventHandler`, 6th of `registerEventHandler`.
- `Event::send()` runs every handler even after an `ERROR`, but an exception thrown in a handler stops the chain and propagates to the caller.

## Checklist

- [ ] Handler signature matches the dispatcher (`Event` vs raw `&$arFields`).
- [ ] Every `registerEventHandler` has an `unRegisterEventHandler` with identical args in uninstall.
- [ ] Named args use kernel names (`fromModuleId`, not `fromModule`).
- [ ] Handlers catch and log their own exceptions; heavy work goes to Messenger (`bitrix-background-jobs`).
- [ ] Own events live in `lib/Public/Event/`, handlers in `lib/Internals/Integration/<Module>/EventHandler/`.
