---
name: bitrix-validation
description: 'Используй для проверки ввода Битрикса: атрибуты NotEmpty/Length, DTO и ValidationService. Валидация параметров контроллера, сервисов и CLI, свои правила и ошибки.'
---

# Валидация

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

Всё пространство имён доступно **Since main 24.300**. Раньше проверяй ввод вручную в сервисе и возвращай ошибки `Result` (`bitrix-result-and-errors`).

| Задача | Читать |
| --- | --- |
| Атрибуты правил, DTO, вложенные объекты, коллекции, группы, `ValidationService` | `rules/rules-dto.md` |
| Ввод контроллера: правила на параметрах, DTO `ValidationParameter` | `rules/controller.md` |
| Своё правило-атрибут, валидатор, коды ошибок | `rules/custom.md` |

## Обязательные правила

- Сервис: `ServiceLocator::getInstance()->get('main.validation.service')` либо тип `ValidationService` в конструкторе сервиса (без состояния).
- Нет секции `validation` в `.settings.php` и атрибута `#[ValidationParameter]`.
- Имена: `RegExp`, не `Regex`; `errorMessage`, не `message`. Метода `getField()` у ошибки нет: путь свойства находится в `getCode()` (`email`, `items.0.name`).
- Правила на параметрах действий, `groups`, `#[Json]`, `#[OnlyOneOfPropertyRequired]` доступны **Since main 26.250\***. На 25.x правило параметра молча игнорируется: проверяй DTO.
- `ValidationResult` наследует `Result`: передавай ошибки через `Result` сервиса, не исключением.
