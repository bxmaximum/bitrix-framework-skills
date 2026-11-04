---
name: bitrix-events
description: 'Используй для событий Битрикса: D7 Event/EventManager и старые обработчики (OnBeforeUserAdd, &$arFields). Публикация, обработка, отмена, сигнатуры и регистрация.'
---

# События Битрикса

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

## Способ отправки определяет сигнатуру обработчика

| Отправка (найди в вызывающем коде) | Примеры | Аргументы обработчика | Результат |
| --- | --- | --- | --- |
| `new Event(...)` + `->send()` | Свои события, `main:OnBeforeMailSend`, события таблетов ORM | VERSION 2 (`registerEventHandler`, `addEventHandler`): объект `Event`. VERSION 1 (`*Compatible`): `...array_values($params)` | `$event->getResults()`; результат кроме `EventResult` оборачивается как `UNDEFINED` |
| `GetModuleEvents()` + `ExecuteModuleEventEx()` (старое API) | `main:OnBeforeUserAdd`, `OnAfterUserAdd`, `OnPageStart`, `OnEpilog`, `iblock:OnBeforeIBlockElementAdd` | Исходные аргументы при любой зарегистрированной VERSION, например `array &$arFields` | Зависит от вызывающего кода |

- В старых `OnBefore*` отмена — `$APPLICATION->ThrowException('…'); return false;` (проверяется `=== false`). Меняй данные через ссылку `&$arFields`; возвращённый массив (`['FIELDS' => …]`) игнорируется.
- События таблетов ORM / записей highloadblock используют `Bitrix\Main\ORM\EventManager` и `ORM\EventResult::modifyFields()` (`bitrix-orm`); имена событий HL — `bitrix-highloadblock`.

## Отправка события D7

`php bitrix.php make:event PostCreated -m vendor.blog -C Post` (**Since main 25.900**; раньше пиши класс вручную) создаёт `Vendor\Blog\Public\Event\Post\PostCreatedEvent` (без `-C`: `…\Public\Event\`). Генератор не добавляет параметры — задай их:

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
        // вызывающий код решает: прервать, записать лог или собрать ошибки
    }
}
```

Отмена с причиной: верни `new EventResult(EventResult::ERROR, ['error' => new Error('…', 'CODE')])`. Отказ учитывают только вызывающие методы, читающие результат (почта: `bitrix-mail`).

## Обработка

`php bitrix.php make:eventhandler PostCreated --event-module=vendor.blog -m vendor.notify`: `name` — имя **события**, `-m` — модуль обработчика; результат `Vendor\Notify\Internals\Integration\Vendor\Blog\EventHandler\PostCreatedEventHandler::handle(PostCreatedEvent $event)`. Добавь отсутствующий `use` класса события.

```php
public static function handle(PostCreatedEvent $event): EventResult
{
    $notifier = ServiceLocator::getInstance()->get(Notifier::class);
    $result = $notifier->notifyAuthor($event->authorId);

    return new EventResult($result->isSuccess() ? EventResult::SUCCESS : EventResult::ERROR, $result->getErrorMessages());
}
```

Обработчики — статические функции обратного вызова; контейнер их не создаёт. Получай сервисы внутри (`bitrix-framework`).

## Регистрация

Постоянная (установщик модуля; в `DoUninstall` удали с теми же аргументами):

```php
EventManager::getInstance()->registerEventHandler(
    fromModuleId: 'vendor.blog',
    eventType: 'PostCreated',
    toModuleId: 'vendor.notify',
    toClass: PostCreatedEventHandler::class,
    toMethod: 'handle',
);
```

- `registerEventHandlerCompatible()` (те же аргументы, VERSION 1) заставляет D7-событие передать `...array_values($params)` вместо `Event`. Для старых событий `ExecuteModuleEventEx` оба метода передают исходные аргументы; ядро по соглашению использует для них `*Compatible`.
- **Since main 26.600\*** обработчики объявляются в `install/migrations/events.php` (`register()` = VERSION 2, `registerCompatible()` = VERSION 1), см. `bitrix-modules`.
- Только на время запроса: `addEventHandler($fromModuleId, $eventType, $callback, $includeFile = false, $sort = 100)` (VERSION 2) / `addEventHandlerCompatible()` (VERSION 1), например в `init.php`.
- Порядок — по возрастанию `$sort` (по умолчанию 100): пятый аргумент `addEventHandler`, шестой `registerEventHandler`.
- `Event::send()` выполняет все обработчики даже после `ERROR`, но исключение по умолчанию прерывает цепочку и передаётся вызывающему коду. При `Event::isDebugOn()` исключения `Exception` добавляются в событие, и цепочка продолжается; PHP `Error` этот перехват не охватывает.

## Проверка

- [ ] Сигнатура соответствует способу отправки (`Event` или исходные `&$arFields`).
- [ ] Каждому `registerEventHandler` соответствует `unRegisterEventHandler` с теми же аргументами при удалении модуля.
- [ ] Именованные аргументы используют имена ядра (`fromModuleId`, не `fromModule`).
- [ ] Обработчик перехватывает и пишет свои исключения; тяжёлая работа уходит в Messenger (`bitrix-background-jobs`).
- [ ] Свои события находятся в `lib/Public/Event/`, обработчики — в `lib/Internals/Integration/<Module>/EventHandler/`.
