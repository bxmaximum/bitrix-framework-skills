# Messenger: сообщение, получатель, отправка

**Since main 25.100.300**, альфа: API может меняться. На старом ядре используй агенты и свою таблицу.

Порядок: `$message->send('queue')` → брокер (таблица БД) → воркер берёт пакет → `process()` получателя → подтверждение / повтор / отклонение.

## Сообщение

`php bitrix.php make:message SendWelcome -m vendor.module` (**Since main 25.900**; на старом ядре класс пишется вручную) создаёт `Vendor\Module\Public\Message\SendWelcomeMessage`.

```php
namespace Vendor\Module\Public\Message;

use Bitrix\Main\Messenger\Entity\AbstractMessage;

final class SendWelcomeMessage extends AbstractMessage
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {}
}
```

- `AbstractMessage` сериализует открытые свойства в JSON и восстанавливает объект по именам параметров конструктора; переопределять `jsonSerialize()`/`createFromData()` не нужно.
- Только значения, представимые в JSON (`int`, `string`, `float`, `bool`, массивы); без `EntityObject`, замыканий и ресурсов.
- Включай в сообщение всё нужное получателю: сущность может измениться или исчезнуть до обработки.

## Получатель

`php bitrix.php make:messagehandler SendWelcome --message-module=vendor.module -m vendor.module` создаёт `Vendor\Module\Internals\Integration\Vendor\Module\MessageHandler\SendWelcomeMessageHandler` (`-m` — модуль обработчика).

```php
use Bitrix\Main\Messenger\Entity\MessageInterface;
use Bitrix\Main\Messenger\Internals\Exception\Receiver\UnprocessableMessageException;
use Bitrix\Main\Messenger\Receiver\AbstractReceiver;

final class SendWelcomeMessageHandler extends AbstractReceiver
{
    public function __construct(private readonly Mailer $mailer)
    {
        // У AbstractReceiver нет конструктора: не вызывай parent::__construct()
    }

    protected function process(MessageInterface $message): void
    {
        if (!$message instanceof SendWelcomeMessage)
        {
            throw new UnprocessableMessageException($message);
        }

        $this->mailer->sendWelcome($message->userId, $message->email);
    }
}
```

- Воркер создаёт получатель через `ServiceLocator::getInstance()->get($handlerClass)` после `Loader::requireModule()` модуля очереди: зависимости конструктора собираются автоматически. Не регистрируй его в `services` только с `className`: это отключит автосборку и вызовет `ArgumentCountError`. Регистрация нужна для скаляров или привязки интерфейсов (`bitrix-service-locator`).
- При успехе `process()` возвращает `void`. Результат определяется исключением (`Bitrix\Main\Messenger\Internals\Exception\Receiver\*`):

| Исключение | Результат |
| --- | --- |
| Нет | Подтверждение: сообщение обработано |
| `RecoverableMessageException` | Повтор через `getRetryDelay()` секунд; не расходует `max_retries`, ограничивай самостоятельно |
| `UnrecoverableMessageException` | Удаление без повторов; уведомление в логе `main.Messenger.Receiver` |
| `UnprocessableMessageException` или иной `Throwable` | Повторы по `retry_strategy` до `max_retries`, затем удаление; запись в лог исключений |

- Внутри `process()` глобальный `$USER` равен `null`; передавай ID пользователя сообщением.

## Отправка

```php
use Bitrix\Main\Messenger\Entity\ProcessingParam\DelayParam;
use Bitrix\Main\Messenger\Entity\ProcessingParam\ItemIdParam;

(new SendWelcomeMessage($userId, $email))->send('vendor_welcome');
(new SendWelcomeMessage($userId, $email))->send('vendor_welcome', [
    new DelayParam(3600),                   // секунды
    new ItemIdParam('welcome-' . $userId),  // бизнес-ключ сообщения
]);
```

- `send()` выбрасывает `ConfigurationException` для неизвестной очереди и `SendFailedException` при ошибке сохранения.
- Шины `dispatch()` в стиле Symfony нет; используй `$message->send($queueId)`.
- Очередь должна быть объявлена: `rules/messenger-config.md`.
