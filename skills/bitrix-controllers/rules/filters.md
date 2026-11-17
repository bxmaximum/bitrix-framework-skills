# Фильтры

## Стандартные фильтры

`Controller::getDefaultPreFilters()` возвращает `Authentication`, `HttpMethod([GET, POST])` и `Csrf`. `JsonController` добавляет `ContentType([JSON])`. При переопределении начинай с `parent::getDefaultPreFilters()`. От него зависят контроллеры REST v3.

В main 26.1000.0 для POST ядро добавляет отсутствующий `Csrf`, если базовый `prefilters` содержит `HttpMethod`, разрешающий POST. Это происходит до обработки `+prefilters` и `-prefilters`. При замене набора фильтров для AJAX POST укажи `Csrf` явно.

Встроенные `Bitrix\Main\Engine\ActionFilter\*`:

- `Authentication($enableRedirect = false)`: 401 для гостей.
- `Csrf`: параметр `sessid` или заголовок `X-Bitrix-Csrf-Token`. Выполняется только в `SCOPE_AJAX` (AJAX и контроллеры маршрутов), включая GET.
- `HttpMethod([...])`, `ContentType([...])`, `CloseSession` (parallel AJAX), `Cors`, `Scope(Scope::NOT_REST)`.
- `Token`: заголовки с подписанным токеном сущности; специальный сценарий.
- `AccessCheck`: через `#[ActionAccess]`, см. ниже.

Если проверки не хватает, напиши один подкласс `ActionFilter\Base` (ниже), а не копируй проверки по действиям.

## Атрибуты (**Since main 26.250\***)

`ActionFilter\Attribute\Rule\*`:

- `Authentication`, `HttpMethod`, `Csrf`, `ContentType`, `CloseSession`, `Cors`, `Scope`, `Token` добавляют фильтр (`+prefilters`). Последний параметр конструктора `FilterType $type` позволяет удалить его (`FilterType::DisablePrefilter`).
- `EnablePrefilters([...])` / `DisablePrefilters([...FQCN])`; аналогично для последующих фильтров. `Prefilters([...])` / `Postfilters([...])` заменяют стандартный набор.
- `#[HttpMethod]` без аргументов разрешает только GET. Второй аргумент — `FilterType`, а не метод. Пример документации `HttpMethod(METHOD_GET, METHOD_POST)` неверен.
- Удаление выполняется перед добавлением; сопоставление по классу (`is_a`).

```php
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\EnablePrefilters;

#[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])]
#[EnablePrefilters([new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET])])]
public function pingAction(): array { return ['ok' => true]; } // публичное действие, только чтение
```

## `configureActions()` (любая версия)

```php
public function configureActions(): array
{
    return [
        'submit' => [
            '-prefilters' => [ActionFilter\HttpMethod::class],
            '+prefilters' => [new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST])],
        ],
    ];
}
```

Ключи `prefilters` / `postfilters` заменяют стандартный набор, `+…` добавляет, `-…` удаляет по FQCN. У настроенного здесь действия не должно быть атрибутов фильтров.

## `#[ActionAccess]` (**Since main 26.600\***)

Используй, когда у модуля есть контроллер доступа (`Bitrix\Main\Access\AccessibleController`, например `BaseAccessController`). Контроллер должен реализовать `Engine\Contract\AccessCheckControllerInterface`, иначе `SystemException`.

```php
use Bitrix\Main\Access\AccessibleController;
use Bitrix\Main\Engine\ActionFilter\Attribute\Access\ActionAccess;
use Bitrix\Main\Engine\Contract\AccessCheckControllerInterface;

final class Post extends Controller implements AccessCheckControllerInterface
{
    public function getAccessController(): AccessibleController
    {
        return PostAccessController::getInstance((int)$this->getCurrentUser()?->getId());
    }

    #[ActionAccess(PostAction::Edit, strategyArgs: ['itemIdRequestKey' => 'id'])]
    public function updateAction(int $id, string $title): ?array { /* ... */ }
}
```

- `action` — строка или enum (со значением → значение, без значения → имя варианта). Стандартная `ItemIdFromRequestStrategy` читает ключ запроса `id` и вызывает `checkByItemId()`. Своя стратегия реализует `AccessCheckStrategyInterface`.
- HTTP 403 по умолчанию не устанавливается (`AccessCheck`: `setHttpStatus=false`); ответ получает только ошибку.
- До 26.600 используй фильтр `Base`, вызывающий `$access->checkByItemId($action, $id)`.

Для списков вкладывай фильтр доступа отдельно, чтобы пользовательские ключи или `'LOGIC' => 'OR'` не могли его обойти: `'filter' => [$userFilter, $access->getEntityFilter($action, PostTable::class) ?? []]`. Один фильтр используй для выборки и `count`.

## Свой фильтр

```php
use Bitrix\Main\Context;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

final class RequireGroup extends ActionFilter\Base
{
    public function __construct(private readonly int $groupId)
    {
        parent::__construct();
    }

    public function onBeforeAction(Event $event)
    {
        // getUserGroups() возвращает ID групп
        if (!in_array($this->groupId, array_map('intval', CurrentUser::get()->getUserGroups()), true))
        {
            Context::getCurrent()->getResponse()->setStatus(403);
            $this->addError(new Error('Доступ запрещён', 'ACCESS_DENIED'));

            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        return null;
    }
}
// #[EnablePrefilters([new RequireGroup(5)])] или '+prefilters' => [new RequireGroup(5)]
```
