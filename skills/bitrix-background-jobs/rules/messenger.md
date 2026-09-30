# Messenger: message, receiver, dispatch

**Since main 25.100.300**, alpha: API may change. Before that: agents + own table.

Flow: `$message->send('queue')` → broker (DB table) → worker takes a batch → receiver `process()` → ack / retry / reject.

## Message

`php bitrix.php make:message SendWelcome -m vendor.module` (**Since main 25.900**; older: write the class) → `Vendor\Module\Public\Message\SendWelcomeMessage`.

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

- `AbstractMessage` serializes public properties to JSON and rebuilds the object by constructor parameter names — no need to override `jsonSerialize()`/`createFromData()`.
- Only JSON-safe values (`int`, `string`, `float`, `bool`, arrays); no `EntityObject`, closures or resources.
- Put everything the receiver needs into the message; the entity may change or disappear before processing.

## Receiver

`php bitrix.php make:messagehandler SendWelcome --message-module=vendor.module -m vendor.module` → `Vendor\Module\Internals\Integration\Vendor\Module\MessageHandler\SendWelcomeMessageHandler` (`-m` = handler module).

```php
use Bitrix\Main\Messenger\Entity\MessageInterface;
use Bitrix\Main\Messenger\Internals\Exception\Receiver\UnprocessableMessageException;
use Bitrix\Main\Messenger\Receiver\AbstractReceiver;

final class SendWelcomeMessageHandler extends AbstractReceiver
{
    public function __construct(private readonly Mailer $mailer)
    {
        // AbstractReceiver has no constructor: do not call parent::__construct()
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

- The worker builds the receiver with `ServiceLocator::getInstance()->get($handlerClass)` after `Loader::requireModule()` of the queue's module → constructor deps are autowired. Don't register it in `services` with `className` only (that disables autowire → `ArgumentCountError`); register only to pass scalars or bind interfaces (`bitrix-service-locator`).
- `process()` returns `void` on success. Outcome by exception (`Bitrix\Main\Messenger\Internals\Exception\Receiver\*`):

| Throw | Effect |
| --- | --- |
| nothing | ack, message done |
| `RecoverableMessageException` | retried after `getRetryDelay()` s; does not use up `max_retries` — cap it yourself |
| `UnrecoverableMessageException` | deleted, no retries (notice in `main.Messenger.Receiver` log) |
| `UnprocessableMessageException` or any other `Throwable` | retried by the queue `retry_strategy` until `max_retries`, then deleted; written to the exception log |

- `global $USER` is `null` inside `process()`; pass the user ID in the message.

## Dispatch

```php
use Bitrix\Main\Messenger\Entity\ProcessingParam\DelayParam;
use Bitrix\Main\Messenger\Entity\ProcessingParam\ItemIdParam;

(new SendWelcomeMessage($userId, $email))->send('vendor_welcome');
(new SendWelcomeMessage($userId, $email))->send('vendor_welcome', [
    new DelayParam(3600),                   // seconds
    new ItemIdParam('welcome-' . $userId),  // business key of the message
]);
```

- `send()` throws if the queue is unknown (`ConfigurationException`) or storage fails (`SendFailedException`).
- No Symfony-style bus `dispatch()`; always `$message->send($queueId)`.
- Queue must be declared: `rules/messenger-config.md`.
