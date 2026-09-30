# Files, action names, autowire

## Files and action names

Module `.settings.php` (`/local/modules/vendor.module/.settings.php`):

```php
'controllers' => [
    'value' => [
        'defaultNamespace' => '\\Vendor\\Module\\Infrastructure\\Controller',
        'namespaces' => [
            '\\Vendor\\Module\\Infrastructure\\Controller\\Web' => 'web', // alias segment
        ],
        'restIntegration' => ['enabled' => true], // only for the REST scope
    ],
    'readonly' => true,
],
```

- `lib/Infrastructure/Controller/Post.php` → action `vendor:module.Post.create` (`Post::createAction`), URL `/bitrix/services/main/ajax.php?action=vendor:module.Post.create`.
- `Web\Post::getAction` → `vendor:module.web.Post.get`. No `Controller` suffix is added or stripped.
- Case: if the controller segment starts lower-case, the resolver lower-cases the whole default-namespace path, and the loader then looks for `lib/infrastructure/controller/post.php`. With PascalCase files that works on macOS and returns "action not found" on Linux. Kernel: `Engine/Resolver.php` `buildControllerClassName()`, `Loader::autoLoad()`.

## Minimal controller

The engine builds controllers with `Request` only (`ControllerBuilder`). No constructor DI.

```php
<?php declare(strict_types=1);

namespace Vendor\Module\Infrastructure\Controller;

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Vendor\Module\Application\Service\PostService;

final class Post extends Controller
{
    #[HttpMethod([ActionFilter\HttpMethod::METHOD_GET])]
    #[DisablePrefilters([ActionFilter\Csrf::class])] // read-only; Authentication stays
    public function getAction(int $id, PostService $posts): ?array
    {
        $post = $posts->find($id);
        if ($post === null)
        {
            $this->addError(new Error('Post not found', 'POST_NOT_FOUND'));
            return null;
        }

        return ['post' => $post];
    }

    #[HttpMethod([ActionFilter\HttpMethod::METHOD_POST])] // defaults keep Authentication + Csrf
    public function createAction(string $title, PostService $posts, CurrentUser $user): ?array
    {
        $result = $posts->create($title, (int)$user->getId());
        if (!$result->isSuccess())
        {
            $this->addErrors($result->getErrors());
            return null;
        }

        return $result->getData();
    }
}
```

Attributes need main 26.250\*; the same config for older kernels: `rules/filters.md` → `configureActions()`. `JsonController` = `Controller` + `ContentType([JSON])` prefilter.

Lifecycle: `__construct(Request)` → `init()` (builds the filter config; call `parent::init()` first) → `processBeforeAction()` → prefilters → action → postfilters, `processAfterAction()` → response. `processBeforeAction()` runs before `Authentication`/`Csrf`: never put side effects or rights-dependent code there. Keep logic out of these hooks. Component controllers (`class.php` implementing `Controllerable`) get `onPrepareComponentParams()` for AJAX actions, never `executeComponent()` (`bitrix-components`).

## Action parameters (`Engine\AutoWire\Binder`)

Class-typed parameter, first match wins:

1. Controller `getPrimaryAutoWiredParameter()` / `getAutoWiredParameters()` and the global ones: `PageNavigation` (id `nav`), `JsonPayload`, `CurrentUser`. Subclasses match too.
2. Backed enum → `tryFrom()` of the request value with the parameter's name.
3. The parameter's default value.
4. `ServiceLocator::get(FQCN)`, but only when `class_exists()`. An interface type is never resolved, even if registered. Type-hint the concrete class.

Scalars and `array`: POST form, then query string, by name (header `BX-AJAX-QB` → from the `bx_data` JSON), then the default value. Not from `$_FILES`; not from a JSON body unless the `ContentType([JSON])` prefilter ran (`JsonController`), which adds the body's top-level keys as a last source. Otherwise take `JsonPayload $payload` and read `$payload->getData()`. Missing or wrong type → `BinderArgumentException` → error response. Union types are rejected.

Not injectable: `HttpRequest` (error) → `$this->getRequest()`; `Session` (the locator builds a new object, not the app session) → `Application::getInstance()->getSession()`. `HttpRequest` and `Route` are injected only into closure routes (`bitrix-routing`). Validated request DTO: `ValidationParameter` (`bitrix-validation`).

### Loading an entity by id

There is no built-in ORM binding. Declare one; a closure with a second argument receives the request value `<paramName>Id`:

```php
public function getAutoWiredParameters(): array
{
    return [
        new \Bitrix\Main\Engine\AutoWire\Parameter(
            Post::class, // PostTable::getObjectClass()
            fn (string $className, $id) => PostTable::getById((int)$id)->fetchObject(),
        ),
    ];
}

public function viewAction(Post $post): array { /* request key: postId */ }
```

`null` from the closure → error, unless the parameter is nullable. Check rights on the loaded object; take its type/owner from the record, never from the request (`bitrix-security`).

## PageNavigation

`PageNavigation $nav` is built from the query key `nav`: sizes 1–50 (other `size` → default 20), no record count, `page-all` ignored, page not clamped. Pass `$nav->getOffset()`/`getLimit()` to the service, then `$nav->setRecordCount($total)`. Another id, other sizes or two lists → `new PageNavigation('my-nav')` yourself. Don't type-hint `ReversePageNavigation`/`AdminPageNavigation`: the global factory returns the base class → `TypeError`.

## Front-end call

```js
BX.ajax.runAction('vendor:module.Post.list', {
    data: { sectionId: 5 },
    navigation: { page: 3, size: 20 }, // → ?nav=page-3-size-20
}).then((response) => response.data, (response) => response.errors);
```
