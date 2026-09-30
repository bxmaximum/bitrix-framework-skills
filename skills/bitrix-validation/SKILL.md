---
name: bitrix-validation
description: 'Bitrix\Main\Validation — rule attributes (NotEmpty, Length, Email, RegExp…), ValidationService, ValidationParameter request DTOs, rules on action params, groups, custom validators. Use when validating controller, service or CLI input.'
---

# Validation

Baseline: main 23.0+ · Verified: main 26.800.0

The whole namespace is **Since main 24.300**. Before that, check input by hand in the service and return `Result` errors (`bitrix-result-and-errors`).

| Task | Read |
| --- | --- |
| Rule attributes, DTOs, nested objects, collections, groups, `ValidationService` | `rules/rules-dto.md` |
| Controller input: rules on action params, `ValidationParameter` DTOs | `rules/controller.md` |
| Custom rule attribute and validator, error codes | `rules/custom.md` |

## Invariants

- Service: `ServiceLocator::getInstance()->get('main.validation.service')`, or type-hint `ValidationService` in a service constructor (stateless).
- There is no `.settings.php` `validation` section and no `#[ValidationParameter]` attribute.
- Names: `RegExp` (not `Regex`), `errorMessage` (not `message`). Errors have no `getField()`: the property path is in `getCode()` (`email`, `items.0.name`).
- Rules on action params, `groups`, `#[Json]`, `#[OnlyOneOfPropertyRequired]` are **Since main 26.250\***. On 25.x a rule on an action param is silently ignored: validate a DTO instead.
- `ValidationResult` extends `Result`: pass its errors up via the service `Result`, don't throw.
