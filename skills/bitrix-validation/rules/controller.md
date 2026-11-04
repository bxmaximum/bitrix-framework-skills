# Проверка ввода контроллера

## Правила параметров действий (**Since main 26.250\***)

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\PositiveNumber;

final class User extends Controller
{
    public function renameAction(#[PositiveNumber] int $id, #[Length(min: 2, max: 64)] string $name): array
    {
        return ['id' => $id];
    }
}
```

- Привязчик проверяет до вызова (`ValidationChecker`). Сообщает только первое нарушенное правило одной ошибкой: `Invalid value to match parameter: [<param>] <message>.`. Для ошибок по полям используй DTO.
- На 25.x атрибуты правил применимы только к свойствам: на параметре игнорируются без ошибки. Проверь версию ядра либо проверяй DTO.

## DTO запроса через `ValidationParameter` (24.300+)

`Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter` — AutoWire `Parameter`: создаёт объект твоим замыканием, проверяет его; при отказе действие не вызывается, возвращаются все ошибки.

```php
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter;

final class Post extends Controller
{
    public function getAutoWiredParameters(): array
    {
        return [
            new ValidationParameter(
                CreatePostRequest::class,
                fn () => CreatePostRequest::fromArray($this->getRequest()->getPostList()->getValues()),
            ),
        ];
    }

    public function createAction(CreatePostRequest $request, PostService $posts): ?array
    {
        $result = $posts->create($request); // только после успешной проверки
        if (!$result->isSuccess())
        {
            $this->addErrors($result->getErrors());
            return null;
        }

        return $result->getData();
    }
}
```

```php
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;

final class CreatePostRequest
{
    public function __construct(
        #[NotEmpty, Length(max: 255)]
        public readonly ?string $title = null,
        public readonly ?string $body = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['title']) ? (string)$data['title'] : null,
            isset($data['body']) ? (string)$data['body'] : null,
        );
    }
}
```

- Выбирай источник явно: форма `getPostList()`, тело JSON `getJsonList()` (не входит в `get()`/`getPost()`).
- Каркас: `php bitrix/bitrix.php make:request CreatePost -m vendor.blog --fields=title,body` (**Since main 25.900**).

## Ошибки нижних слоёв

Сервис может выбросить `new \Bitrix\Main\Validation\ValidationException($validation->getErrors())`: контроллер добавит каждую ошибку в ответ (**Since main 26.250\***). На старом ядре верни `Result` и передай его ошибки в `addErrors()`.
