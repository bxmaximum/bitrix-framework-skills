# Validating controller input

## Rules on action params (**Since main 26.250\***)

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

- The binder validates before the call (`ValidationChecker`). Only the first failed rule is reported, as one error: `Invalid value to match parameter: [<param>] <message>.`. Use a DTO when the client needs per-field errors.
- On 25.x the rule attributes target properties only, so on a parameter they are ignored without any error. Check the kernel version or validate a DTO.

## Request DTO via `ValidationParameter` (24.300+)

`Bitrix\Main\Validation\Engine\AutoWire\ValidationParameter` is an AutoWire `Parameter`: it builds the object with your closure, validates it, and on failure the action is not called and all errors are returned.

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
        $result = $posts->create($request); // reached only when valid
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

- Pick the source explicitly: form `getPostList()`, JSON body `getJsonList()` (not part of `get()`/`getPost()`).
- Generate the skeleton: `php bitrix/bitrix.php make:request CreatePost -m vendor.blog --fields=title,body` (**Since main 25.900**).

## Errors from deeper layers

A service may `throw new \Bitrix\Main\Validation\ValidationException($validation->getErrors())`: the controller adds each error to the response (**Since main 26.250\***). On older kernels, return a `Result` and `addErrors()` it.
