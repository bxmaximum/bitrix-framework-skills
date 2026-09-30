---
name: bitrix-result-and-errors
description: 'Bitrix\Main\Result, Error, ErrorCollection, ORM AddResult/UpdateResult/DeleteResult, EventResult; Result vs exceptions, error codes, errors in controllers. Use when designing service APIs or passing errors across module boundaries.'
---

# Result and Error

Baseline: main 23.0+ · Verified: main 26.800.0

## Contract

- Expected failures (validation, business rule, not found, no rights) → `Result` with `Error`s.
- Broken state or contract (module missing, DB down, misconfiguration, bug) → exception.
- One method, one contract: always a `Result`, or a value plus exceptions for the exceptional case. Never `bool`/`null`/`-1`/`['error' => …]`.

## `Bitrix\Main\Result`

```php
use Bitrix\Main\Error;
use Bitrix\Main\Result;

$result = new Result();
if ($title === '')
{
    return $result->addError(new Error('Title required', 'BLOG_POST_TITLE_EMPTY', ['field' => 'title']));
}

return $result->setData(['id' => $id]);
```

- `isSuccess()`, `addError(Error)`, `addErrors(Error[])`, `getErrors()`, `getError()` (first or `null`), `getErrorMessages()`, `getErrorCollection()`, `setData(array)`, `getData()`. Setters return `$this`.
- `setData()` silently drops `SqlExpression` values.

## `Error` and codes

`new Error($message, $code = 0, $customData = null)`. JSON form: `{"message", "code", "customData"}`.

- Codes are stable and machine-readable: module + entity + reason (`BLOG_POST_NOT_FOUND`), kept in constants. Clients branch on the code, not the text.
- Messages via `Loc::getMessage('BLOG_ERROR_POST_TITLE_EMPTY')`.
- `ErrorCollection`: `new ErrorCollection([$error])`, `setError()`, `add([...])`, `getErrorByCode('CODE')`, iterable, `isEmpty()`, `toArray()`.

## Typed result

Give callers getters instead of `getData()['post']`:

```php
final class CreatePostResult extends \Bitrix\Main\Result
{
    private ?Post $post = null;

    public function setPost(Post $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }
}
```

## ORM results (`Bitrix\Main\ORM\Data\*`)

- `AddResult`: `getId()`, `getPrimary()`, `getObject()`.
- `UpdateResult`: `getId()`, `getPrimary()`, `getAffectedRowsCount()`.
- `DeleteResult`: no methods of its own (no affected-rows count).
- `Bitrix\Main\Entity\*Result` are compat aliases. Write `ORM\Data\*`.
- A failed ORM result whose errors nobody read (`isSuccess()` / `getErrors()`) raises `E_USER_WARNING` on destruct. Always check it and return it, or copy its errors up.

```php
$add = PostTable::add(['TITLE' => $title]);
if (!$add->isSuccess())
{
    return (new Result())->addErrors($add->getErrors());
}
$id = $add->getId();
```

## Other result types

- `EventResult` (`SUCCESS`, `ERROR`, `UNDEFINED`) is a handler's answer, not a service result: `bitrix-events`.
- `ValidationResult` (`bitrix-validation`) extends `Result`: add its errors to the service result.

## Controllers

`$this->addErrors($result->getErrors()); return null;` gives `{"status":"error","data":null,"errors":[…]}`. Envelope details: `bitrix-controllers`.

## Exceptions at the boundary

Catch at the module/controller edge, log, return a generic error:

```php
try
{
    return $this->service->run($dto);
}
catch (\Bitrix\Main\SystemException $e)
{
    $this->logger->error('Run failed', ['exception' => $e]);
    return (new Result())->addError(new Error('Internal error', 'VENDOR_INTERNAL_ERROR'));
}
```

- Never swallow `\Throwable` without logging.
- Developer mistakes (`InvalidArgumentException`, `LogicException`, `TypeError`) and "can't happen" domain states (division by zero in a money type) stay exceptions.
