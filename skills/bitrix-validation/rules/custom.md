# Custom rules, errors

## Property/parameter rule

An attribute extends `AbstractPropertyValidationAttribute` and returns validators; a validator implements `ValidatorInterface::validate(mixed $value): ValidationResult`. No registration step.

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
        // must match the trait property type exactly (PHP property types are invariant)
        protected string|LocalizableMessageInterface|null $errorMessage = null,
    ) {}

    protected function getValidators(): array
    {
        return [new EvenNumberValidator()]; // errorMessage replaces their errors automatically
    }
}

final class EvenNumberValidator implements ValidatorInterface
{
    public function validate(mixed $value): ValidationResult
    {
        $result = new ValidationResult();
        if (!is_int($value) || $value % 2 !== 0)
        {
            // code gets the property path prepended: 'age.EVEN_NUMBER'
            $result->addError(new ValidationError('Number must be even', 'EVEN_NUMBER', failedValidator: $this));
        }

        return $result;
    }
}
```

Usage: `#[EvenNumber(errorMessage: 'Age must be even')] public ?int $age = null;`.

- `TARGET_PARAMETER` matters only on main 26.250\* and later, where action params are validated.
- Group support (**Since main 26.250\***): implement `Rule\ValidateByGroupInterface::getGroups(): array` and accept `protected array $groups = []`.
- Rule across several properties: extend `AbstractClassValidationAttribute` (target class) or implement `ClassValidationAttributeInterface` with `validateObject(object $object): ValidationResult`.

## Reading errors

`ValidationResult` extends `Bitrix\Main\Result`; errors are `ValidationError` (extends `Error`):

- `getMessage()`: text (localized kernel phrases).
- `getCode()`: property path (`email`, `items.0.name`) plus the validator's own code after a dot.
- `getFailedValidator()`: the validator instance or `null`.

Map them into your module's error codes at the boundary if clients need stable codes (`bitrix-result-and-errors`).
