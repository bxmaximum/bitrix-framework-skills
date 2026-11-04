# Атрибуты правил и DTO

Пространство имён `Bitrix\Main\Validation\Rule\`.

| Атрибут | Проверка |
| --- | --- |
| `NotEmpty` | `!empty`; `allowZero`, `allowSpaces` |
| `Length(min, max)` | Длина строки |
| `Min(n)` / `Max(n)` / `Range(min, max)` | Числа (целочисленные границы) |
| `PositiveNumber` | >= 1 (`0.5` не подходит) |
| `Email` / `PhoneOrEmail` | `strict`, `domainCheck` передаются в `check_email()` |
| `Phone`, `Url` | Формат |
| `RegExp('/…/')` | `preg_match`; `flags`, `offset` |
| `InArray([...])` | `strict`, `showValues` (перечень разрешённых значений в ошибке) |
| `Json` (**26.250\***) | Корректная строка JSON |
| `ElementsType(typeEnum: Type::Integer)` / `ElementsType(className: Dto::class)` | Тип элемента массива; `Rule\Enum\Type`: `Integer`, `String`, `Float`, `Numeric` (`is_numeric`) |
| `Recursive\Validatable` | Проверка вложенного объекта; `iterable: true` — каждого элемента |
| `AtLeastOnePropertyNotEmpty([...])` (класс) | Заполнено одно из свойств; `allowZero`, `allowEmptyString` |
| `OnlyOneOfPropertyRequired([...])` (класс, **26.250\***) | Заполнено ровно одно |

Любое правило принимает `errorMessage`: строку или `new LocalizableMessage('PHRASE_CODE', phraseSrcFile: __FILE__)` (`Bitrix\Main\Localization`). **Since main 26.250\*** также принимает `groups: [...]` (строки, enum, `Stringable`).

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

Правила инициализации:

- Неинициализированное nullable-свойство пропускается.
- Неинициализированное свойство, не допускающее null, даёт `MAIN_VALIDATION_EMPTY_PROPERTY`.
- Явный `null` передаётся правилам как значение: nullable-тип не отменяет проверку. Например, `NotEmpty` и `Length` отвергают null, а `Validatable` пропускает его; у `InArray` результат зависит от разрешённых значений и `strict`. Значение по умолчанию null само по себе не делает свойство с правилом необязательным.
- Создавай DTO с nullable-свойствами и значениями по умолчанию null, чтобы конструирование из исходного ввода не бросало исключение.

## Проверка в сервисе

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
            return $result->addErrors($validation->getErrors()); // ValidationError наследует Error
        }
        // ...
        return $result;
    }
}
```

Группы (**Since main 26.250\***): `validate($dto, 'create')` выполняет правила с `create` в `groups` и все правила без групп. Без указания группы выполняются все.

## Вложенные объекты и коллекции

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

Коды ошибок — пути (`items.0.name`). `Validatable` на не-объекте или `iterable` на неперебираемом значении вызывает `ArgumentException`; `null` пропускается.
