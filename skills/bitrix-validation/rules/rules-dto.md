# Rule attributes and DTOs

Namespace `Bitrix\Main\Validation\Rule\`.

| Attribute | Checks |
| --- | --- |
| `NotEmpty` | `!empty`; `allowZero`, `allowSpaces` |
| `Length(min, max)` | string length |
| `Min(n)` / `Max(n)` / `Range(min, max)` | numbers (int bounds) |
| `PositiveNumber` | >= 1 (`0.5` fails) |
| `Email` / `PhoneOrEmail` | `strict`, `domainCheck` (passed to `check_email()`) |
| `Phone`, `Url` | format |
| `RegExp('/…/')` | `preg_match`; `flags`, `offset` |
| `InArray([...])` | `strict`, `showValues` (list allowed values in the error) |
| `Json` (**26.250\***) | valid JSON string |
| `ElementsType(typeEnum: Type::Integer)` / `ElementsType(className: Dto::class)` | array element type; `Rule\Enum\Type`: `Integer`, `String`, `Float`, `Numeric` (`is_numeric`) |
| `Recursive\Validatable` | validate a nested object; `iterable: true` → each element |
| `AtLeastOnePropertyNotEmpty([...])` (class) | one of the listed properties filled; `allowZero`, `allowEmptyString` |
| `OnlyOneOfPropertyRequired([...])` (class, **26.250\***) | exactly one filled |

Every rule takes `errorMessage`: a string or `new LocalizableMessage('PHRASE_CODE', phraseSrcFile: __FILE__)` (`Bitrix\Main\Localization`). **Since main 26.250\***, every rule also takes `groups: [...]` (strings, enums, `Stringable`).

## DTO

```php
use Bitrix\Main\Validation\Rule\Email;
use Bitrix\Main\Validation\Rule\InArray;
use Bitrix\Main\Validation\Rule\Length;
use Bitrix\Main\Validation\Rule\NotEmpty;
use Bitrix\Main\Validation\Rule\Range;

final class CreateUserDto
{
    public function __construct(
        #[NotEmpty, Length(min: 2, max: 64)]
        public readonly ?string $name = null,
        #[NotEmpty, Email]
        public readonly ?string $email = null,
        #[Range(min: 18, max: 120)]
        public readonly ?int $age = null,
        #[InArray(['user', 'admin'], strict: true)]
        public readonly string $role = 'user',
    ) {}
}
```

Initialization rules:

- An uninitialized nullable property is skipped.
- An uninitialized non-nullable property gives `MAIN_VALIDATION_EMPTY_PROPERTY`.
- An explicit `null` counts as a value and goes to the validators. Every kernel value rule rejects it (`Length`, `Range`, `Email`… too, not only `NotEmpty`; only `Validatable` skips `null`), so a `null` default doesn't make a ruled property optional.
- Build DTOs with nullable `null` defaults, so construction from raw input never throws.

## Validating in a service

```php
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Validation\ValidationService;

final class UserService
{
    public function __construct(private readonly ValidationService $validator) {}

    public function register(CreateUserDto $dto): Result
    {
        $result = new Result();
        $validation = $this->validator->validate($dto); // ($object, $group = null)
        if (!$validation->isSuccess())
        {
            return $result->addErrors($validation->getErrors()); // ValidationError extends Error
        }
        // ...
        return $result;
    }
}
```

Groups (**Since main 26.250\***): `validate($dto, 'create')` runs rules with `groups` containing `create` plus all rules without groups. Without a group, every rule runs.

## Nested and collections

```php
use Bitrix\Main\Validation\Rule\AtLeastOnePropertyNotEmpty;
use Bitrix\Main\Validation\Rule\ElementsType;
use Bitrix\Main\Validation\Rule\Recursive\Validatable;

#[AtLeastOnePropertyNotEmpty(['email', 'phone'])]
final class OrderDto
{
    public ?string $email = null;
    public ?string $phone = null;

    /** @var OrderItemDto[] */
    #[ElementsType(className: OrderItemDto::class)]
    #[Validatable(iterable: true)]
    public array $items = [];
}
```

Error codes are paths: `items.0.name`. `Validatable` on a non-object (or `iterable` on a non-iterable) throws `ArgumentException`; `null` is skipped.
