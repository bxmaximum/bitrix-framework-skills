# Engine controllers and classic methods

Endpoint base: `CRestUtil::getEndpoint()` = site + option `rest:rest_server_path` (default `/rest`).

## Engine controller as REST method

`/local/modules/vendor.module/.settings.php`:

```php
return [
    'controllers' => [
        'value' => [
            'defaultNamespace' => '\\Vendor\\Module\\Controller',
            'restIntegration' => [
                'enabled' => true,
                // 'hideModuleScope' => true, // don't advertise the module id as a scope
                // 'scopes' => ['vendor.extra'], // extra scopes for this module
            ],
        ],
        'readonly' => true,
    ],
];
```

`Bitrix\Rest\Engine\RestManager::onFindMethodDescription()` exposes controller actions only for modules with `restIntegration.enabled`. The REST name uses dots: `vendor.module.post.create` for AJAX `vendor:module.post.create`. `CRestServer` lower-cases method names, so the controller path resolves lower-case (`lib/controller/post.php`): PascalCase files (`lib/Controller/Post.php`) give "method not found" on case-sensitive file systems. Controller rules and filters: `bitrix-controllers`.

Limit an action to or away from REST with a scope filter:

```php
use Bitrix\Main\Engine\ActionFilter;

new ActionFilter\Scope(ActionFilter\Scope::REST);     // REST only
new ActionFilter\Scope(ActionFilter\Scope::NOT_REST); // AJAX/CLI only
```

The default `Csrf` prefilter is skipped for REST scope.

## Classic description

Register on install, remove on uninstall (`bitrix-modules`):

```php
\Bitrix\Main\EventManager::getInstance()->registerEventHandler(
    'rest',
    'OnRestServiceBuildDescription',
    'vendor.module',
    \Vendor\Module\Rest\Service::class,
    'onRestServiceBuildDescription',
);
```

```php
namespace Vendor\Module\Rest;

final class Service
{
    public static function onRestServiceBuildDescription(): array
    {
        return [
            'vendor.module' => [ // scope
                'vendor.module.item.get' => [self::class, 'itemGet'],
                // \CRestUtil::EVENTS => [...], \CRestUtil::PLACEMENTS => [...]
            ],
        ];
    }

    public static function itemGet(array $query, int $start, \CRestServer $server): array
    {
        $id = (int)($query['id'] ?? 0);
        if ($id <= 0) {
            throw new \Bitrix\Rest\RestException('id required', \Bitrix\Rest\RestException::ERROR_ARGUMENT);
        }

        return ['id' => $id];
    }
}
```

- Handler args: request params, list offset (`start`), server. Top-level keys are scopes. `\CRestUtil::GLOBAL_SCOPE` (`_global`) needs no scope; use it sparingly.
- `CRestProvider` collects all handlers of the event; method names must be globally unique, prefix them with the module id.
- Kernel example of events in a description: `main/lib/Rest/Handlers.php`.

## Scopes and placements

- `Bitrix\Rest\Engine\ScopeManager` builds the scope list from description keys, `restIntegration` and v3 `rest` sections, cached for 7 days: after adding a scope call `ScopeManager::cleanCache()` (e.g. in the module installer).
- **Since rest 26.600** `placement.bind/unbind/get` work for non-admins when the app access check allows; `USER_ID` may be only self or `0`. Subclasses of `Bitrix\Rest\Api\Placement` must declare `protected static function checkPermission(\CRestServer $server, ?string $permission = null)` (Fatal otherwise).
- **Since rest 26.650** on Bitrix24 cloud plans without the feature, REST events to local apps and user webhooks are silently not sent, and auth fails with `FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN` (403). Don't retry; tell the admin.
