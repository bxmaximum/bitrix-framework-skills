---
name: bitrix-result-and-errors
description: 'Используй для контрактов сервисов Битрикса: Main\Result, Error, ErrorCollection и стабильные коды ошибок. AddResult/UpdateResult ORM, ответы контроллера и границы исключений.'
---

# Result и Error

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

## Контракт

- Ожидаемый отказ (валидация, бизнес-правило, отсутствие записи или прав) → `Result` с `Error`.
- Нарушенное состояние/контракт (нет модуля, БД недоступна, неверная конфигурация, ошибка кода) → исключение.
- Один метод — один контракт: всегда `Result` либо значение с исключениями для исключительных ситуаций. Не смешивай `bool`/`null`/`-1`/`['error' => …]`.

## `Bitrix\Main\Result`

```php
use Bitrix\Main\Error;
use Bitrix\Main\Result;

$result = new Result();
if ($title === '')
{
    return $result->addError(new Error('Нужен заголовок', 'BLOG_POST_TITLE_EMPTY', ['field' => 'title']));
}

return $result->setData(['id' => $id]);
```

- `isSuccess()`, `addError(Error)`, `addErrors(Error[])`, `getErrors()`, `getError()` (первая ошибка либо `null`), `getErrorMessages()`, `getErrorCollection()`, `setData(array)`, `getData()`. Сеттеры возвращают `$this`.
- `setData()` молча отбрасывает значения `SqlExpression`.

## `Error` и коды

`new Error($message, $code = 0, $customData = null)`. Форма JSON: `{"message", "code", "customData"}`.

- Коды стабильны и пригодны для программной обработки: модуль + сущность + причина (`BLOG_POST_NOT_FOUND`), хранятся в константах. Клиент выбирает поведение по коду, не по тексту.
- Сообщения получай через `Loc::getMessage('BLOG_ERROR_POST_TITLE_EMPTY')`.
- `ErrorCollection`: `new ErrorCollection([$error])`, `setError()`, `add([...])`, `getErrorByCode('CODE')`, перебор, `isEmpty()`, `toArray()`.

## Типизированный результат

Предоставляй методы чтения вместо `getData()['post']`:

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

## Результаты ORM (`Bitrix\Main\ORM\Data\*`)

- `AddResult`: `getId()`, `getPrimary()`, `getObject()`.
- `UpdateResult`: `getId()`, `getPrimary()`, `getAffectedRowsCount()`.
- `DeleteResult`: своих методов нет, включая число затронутых строк.
- `Bitrix\Main\Entity\*Result` — псевдонимы совместимости. Используй `ORM\Data\*`.
- Неуспешный результат ORM, ошибки которого никто не прочитал (`isSuccess()` / `getErrors()`), при уничтожении вызывает `E_USER_WARNING`. Всегда проверяй его и возвращай либо передавай ошибки выше.

```php
$add = PostTable::add(['TITLE' => $title]);
if (!$add->isSuccess())
{
    return (new Result())->addErrors($add->getErrors());
}
$id = $add->getId();
```

## Другие результаты

- `EventResult` (`SUCCESS`, `ERROR`, `UNDEFINED`) — ответ обработчика, а не результат сервиса (`bitrix-events`).
- `ValidationResult` (`bitrix-validation`) наследует `Result`: добавляй его ошибки в результат сервиса.

## Контроллеры

`$this->addErrors($result->getErrors()); return null;` даёт `{"status":"error","data":null,"errors":[…]}`. Обёртка описана в `bitrix-controllers`.

## Исключения на границе

Перехватывай на границе модуля/контроллера, записывай в лог и возвращай общую ошибку:

```php
try
{
    return $this->service->run($dto);
}
catch (\Bitrix\Main\SystemException $e)
{
    $this->logger->error('Выполнение завершилось ошибкой', ['exception' => $e]);
    return (new Result())->addError(new Error('Внутренняя ошибка', 'VENDOR_INTERNAL_ERROR'));
}
```

- Не поглощай `\Throwable` без записи в журнал.
- Ошибки разработчика (`InvalidArgumentException`, `LogicException`, `TypeError`) и невозможные состояния предметной области (деление на ноль в денежном типе) остаются исключениями.


## Текст исключений в AJAX (**Since main 26.1000**)

В `Engine\Controller` при `exception_handling.debug=false` обычное исключение становится `Internal server error.` с исходным кодом; PHP `Error` также скрывается. `ArgumentNullException` сохраняет текст с кодом `ERROR_REQUIRED_PARAMETER`, а `BinderArgumentException`, `ArgumentException`, `ValidationException` сохраняют сообщение. Явные `addError(Error)` и ошибки `ValidationException` передаются клиенту: не включай в них SQL, секреты и внутренние пути. При debug=true подробности открыты.

До 26.1000 такого общего скрытия нет: перехватывай исключения на границе и возвращай безопасный `Error`, как выше. Для ожидаемого отказа используй стабильный код `Result`, а не RuntimeException с текстом для пользователя.
