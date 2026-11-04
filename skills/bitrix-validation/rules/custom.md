# Свои правила и ошибки

## Правило свойства/параметра

Атрибут наследует `AbstractPropertyValidationAttribute` и возвращает валидаторы; валидатор реализует `ValidatorInterface::validate(mixed $value): ValidationResult`. Регистрация не нужна.

```php
<?php declare(strict_types=1);

namespace Vendor\Module\Validation;

use Attribute;
use Bitrix\Main\Localization\LocalizableMessageInterface;
use Bitrix\Main\Validation\Rule\AbstractPropertyValidationAttribute;
use Bitrix\Main\Validation\ValidationError;
use Bitrix\Main\Validation\ValidationResult;
use Bitrix\Main\Validation\Validator\ValidatorInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class EvenNumber extends AbstractPropertyValidationAttribute
{
    public function __construct(
        // тип должен точно совпадать со свойством трейта: типы свойств PHP инвариантны
        protected string|LocalizableMessageInterface|null $errorMessage = null,
    ) {}

    protected function getValidators(): array
    {
        return [new EvenNumberValidator()]; // errorMessage автоматически заменяет их ошибки
    }
}

final class EvenNumberValidator implements ValidatorInterface
{
    public function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();
        if (!is_int($value) || $value % 2 !== 0)
        {
            // к коду добавится путь свойства: 'age.EVEN_NUMBER'
            $result->addError(new ValidationError('Число должно быть чётным', 'EVEN_NUMBER', failedValidator: $this));
        }

        return $result;
    }
}
```

Применение: `#[EvenNumber(errorMessage: 'Возраст должен быть чётным')] public ?int $age = null;`.

- `TARGET_PARAMETER` важен только с main 26.250\*, где проверяются параметры действий.
- Поддержка групп (**Since main 26.250\***): реализуй `Rule\ValidateByGroupInterface::getGroups(): array` и принимай `protected array $groups = []`.
- Правило нескольких свойств: наследуй `AbstractClassValidationAttribute` (цель — класс) либо реализуй `ClassValidationAttributeInterface` с `validateObject(object $object): ValidationResult`.

## Чтение ошибок

`ValidationResult` наследует `Bitrix\Main\Result`; ошибки — `ValidationError` (наследник `Error`):

- `getMessage()`: текст (локализованные фразы ядра).
- `getCode()`: путь свойства (`email`, `items.0.name`) и собственный код валидатора после точки.
- `getFailedValidator()`: экземпляр валидатора либо `null`.

Если клиенту нужны стабильные коды, преобразуй в коды своего модуля на границе (`bitrix-result-and-errors`).
