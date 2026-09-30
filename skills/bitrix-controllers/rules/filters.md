# Filters

## Defaults

`Controller::getDefaultPreFilters()` = `Authentication`, `HttpMethod([GET, POST])`, `Csrf`; `JsonController` adds `ContentType([JSON])`. When overriding, start from `parent::getDefaultPreFilters()` (REST v3 controllers depend on it). If the base list allows POST and lacks `Csrf`, a POST request gets `Csrf` added anyway. Only `-prefilters` / `#[DisablePrefilters]` really removes it.

Built-in `Bitrix\Main\Engine\ActionFilter\*`:

- `Authentication($enableRedirect = false)`: 401 for guests.
- `Csrf`: `sessid` param or `X-Bitrix-Csrf-Token` header. Runs only in `SCOPE_AJAX` (AJAX and routed controllers), on GET too.
- `HttpMethod([...])`, `ContentType([...])`, `CloseSession` (parallel AJAX), `Cors`, `Scope(Scope::NOT_REST)`.
- `Token`: signed entity token headers. Niche.
- `AccessCheck`: via `#[ActionAccess]`, see below.

Missing a check? Write one `ActionFilter\Base` subclass (below) instead of copying checks into actions.

## Attributes (**Since main 26.250\***)

`ActionFilter\Attribute\Rule\*`:

- `Authentication`, `HttpMethod`, `Csrf`, `ContentType`, `CloseSession`, `Cors`, `Scope`, `Token` append their filter (`+prefilters`). The last ctor arg `FilterType $type` removes it instead (`FilterType::DisablePrefilter`).
- `EnablePrefilters([...])` / `DisablePrefilters([...FQCN])`, same for postfilters. `Prefilters([...])` / `Postfilters([...])` replace the defaults.
- `#[HttpMethod]` without args = GET only. Its 2nd arg is `FilterType`, not a method. The docs example `HttpMethod(METHOD_GET, METHOD_POST)` is wrong.
- Removal runs before append; removal matches by class (`is_a`).

```php
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\EnablePrefilters;

#[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])]
#[EnablePrefilters([new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET])])]
public function pingAction(): array { return ['ok' => true]; } // public, read-only
```

## `configureActions()` (any version)

```php
public function configureActions(): array
{
    return [
        'get' => [
            '+prefilters' => [new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_GET])],
            '-prefilters' => [ActionFilter\Csrf::class],
        ],
    ];
}
```

Keys: `prefilters` / `postfilters` replace the defaults, `+…` appends, `-…` removes by FQCN. An action configured here must have no filter attributes.

## `#[ActionAccess]` (**Since main 26.600\***)

Use it when the module has an access controller (`Bitrix\Main\Access\AccessibleController`, e.g. `BaseAccessController`). The controller must implement `Engine\Contract\AccessCheckControllerInterface`, otherwise `SystemException`.

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

- `action`: string or enum (backed → value, pure → case name). The default strategy `ItemIdFromRequestStrategy` reads the request key (`id`) and calls `checkByItemId()`. Custom strategy: implement `AccessCheckStrategyInterface`.
- No HTTP 403 by default (`AccessCheck` `setHttpStatus` is `false`), the response only gets the error.
- Fallback before 26.600: a `Base` filter calling `$access->checkByItemId($action, $id)`.

Lists: nest the row filter so user keys or `'LOGIC' => 'OR'` can't override it: `'filter' => [$userFilter, $access->getEntityFilter($action, PostTable::class) ?? []]`. Use the same filter for the list and for `count`.

## Custom filter

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
        // getUserGroups() returns group IDs
        if (!in_array($this->groupId, array_map('intval', CurrentUser::get()->getUserGroups()), true))
        {
            Context::getCurrent()->getResponse()->setStatus(403);
            $this->addError(new Error('Access denied', 'ACCESS_DENIED'));

            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        return null;
    }
}
// #[EnablePrefilters([new RequireGroup(5)])] or '+prefilters' => [new RequireGroup(5)]
```
