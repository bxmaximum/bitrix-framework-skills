---
name: bitrix-service-locator
description: 'Bitrix\Main\DI\ServiceLocator (PSR-11) — services in .settings.php, autowiring rules, interface bindings, factories, overrides, kernel services. Use when wiring dependencies of services, controller actions, commands or handlers.'
---

# ServiceLocator (DI)

Baseline: main 23.0+ · Verified: main 26.800.0

Where constructor DI is allowed (services yes; controllers via action params; commands and handlers no): `bitrix-framework`. Domain classes never touch the container.

## How `get($id)` resolves (**Since main 26.250\***)

| Key | Result |
| --- | --- |
| Built before, or `addInstance()` | the same instance |
| Not registered, class exists | autowired: each ctor param = `get(<its type>)`; a param with a default gets the default |
| Registered interface / abstract class | `className` → implementation **autowired** (`constructorParams` ignored); `constructor` closure → called with `constructorParams` as args |
| Registered concrete class or string id | `className` → `new $class(...array_values(constructorParams))`, **no autowire**; `constructor` closure → called without args |

- Instances are cached for the process unless the entry has `'singleton' => false`.
- A cycle throws `CircularDependencyException`. An untyped, union or scalar param without a default throws `ServiceNotFoundException`.
- Before 26.250: every registered key is built by `new $class(...constructorParams)` or `constructor()`. Unregistered classes are autowired only if all ctor params are concrete classes (no interfaces, defaults ignored). No `singleton` option. On such kernels bind implementations with dependencies through a `constructor` closure.

## Registration

`services` in `/local/modules/vendor.module/.settings.php` (registered on `Loader::includeModule()`) or in the global settings:

```php
<?php
return [
    'services' => [
        'value' => [
            // interface → implementation (autowired since 26.250*)
            \Vendor\Blog\Domain\PostRepositoryInterface::class => [
                'className' => \Vendor\Blog\Infrastructure\OrmPostRepository::class,
            ],
            // scalar arguments → factory
            \Vendor\Blog\Infrastructure\TelegramClient::class => [
                'constructor' => static fn () => new \Vendor\Blog\Infrastructure\TelegramClient(
                    (string)getenv('TELEGRAM_BOT_TOKEN'),
                ),
            ],
            // positional args; closure evaluated on first get()
            'vendor.blog.mailer' => [
                'className' => \Vendor\Blog\Infrastructure\Mailer::class,
                'constructorParams' => static fn () => ['noreply@example.com'],
            ],
        ],
        'readonly' => true,
    ],
];
```

- Don't register a concrete class that only needs other classes. `get(PostService::class)` autowires it, while `[ 'className' => PostService::class ]` alone makes it `new PostService()` → `ArgumentCountError`.
- Prefer FQCN keys. String ids (`main.validation.service`) are kernel style.
- The kernel registers `Application`, `Routing\Router`, `DB\Connection`, `Data\Cache`, `Data\ManagedCache`, `Data\TaggedCache`, `EventManager`, `Data\ConnectionPool`, `\CUserTypeManager` (**Since main 26.250\***), and `Data\Storage\PersistentStorageInterface` (**Since main 25.1100**, `bitrix-storage`). Constructor-inject them instead of calling `Application::getInstance()->…`.

## Overrides

- First registration wins: `registerByModuleSettings()` skips keys that already exist. A later module can't override an earlier one.
- Override a module binding in the global `services` of `/local/.settings_extra.php` (registered at app start, before modules), or at runtime (`init.php`, tests): `ServiceLocator::getInstance()->addInstance($id, $object)` / `addInstanceLazy($id, ['className' => …])`.

## Getting services

- Services: constructor params.
- Controller actions: type-hint the concrete class. Interfaces in action params are not resolved (`bitrix-controllers`).
- Messenger receivers are built with `get(FQCN)`, so they are autowired. Console commands and event handlers are not: call `ServiceLocator::getInstance()->get(Foo::class)` inside `execute()` / the handler.
- `has($id)` is true only for registered or built ids, not for autowirable classes.

## Rules

- Services are singletons per process: no per-request state in properties. Long-running consumers keep instances between messages.
- No `new Service()` in controllers/commands when the container can build it. No container calls in domain code.
- Config goes in as a typed object or scalars through a factory, not as a raw array service.
- Feature toggles: the kernel `Bitrix\Main\Config\Feature` (**Since main 26.700**, `bitrix-storage`), not a home-made flags service.
