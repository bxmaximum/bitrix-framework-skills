# Generators and kernel commands

## `make:*` availability and output (main 26.800)

`<Vendor>\<Module>` = namespace from `-m vendor.module`. Paths are `/local/modules/vendor.module/lib/<namespace tail>/`.

| Command | Since main | Creates |
| --- | --- | --- |
| `make:module vendor.module [--name --description --module-version]` | 25.900 | `/local/modules/vendor.module/` installer skeleton (`bitrix-modules`) |
| `make:controller Post -m vendor.blog [--actions=crud\|list,get] [--alias=api]` | 25.900 (new syntax) | `…\Infrastructure\Controller\Post` |
| `make:tablet b_vendor_post vendor.blog [--namespace=…]` | ≤ 25.100.500 | `…\Tablet\BVendorPostTable`, fields read from the **existing** DB table |
| `make:entity Post -m vendor.blog [--fields=title,body]` | 25.900 | `…\Internals\Entity\Post` |
| `make:service Post -m vendor.blog` | 25.900 | `…\Public\Service\PostService` |
| `make:request CreatePost -m vendor.blog [--fields=…]` | 25.900 | `…\Infrastructure\Controller\Request\CreatePostRequest` |
| `make:event PostCreated -m vendor.blog` | 25.900 | `…\Public\Event\PostCreatedEvent` |
| `make:eventhandler PostCreated --event-module=vendor.blog -m vendor.notify` | 25.900 | `Vendor\Notify\Internals\Integration\Vendor\Blog\EventHandler\PostCreatedEventHandler` |
| `make:message SendWelcome -m vendor.blog` | 25.900 | `…\Public\Message\SendWelcomeMessage` |
| `make:messagehandler SendWelcome --message-module=vendor.blog -m vendor.blog` | 25.900 | `…\Internals\Integration\Vendor\Blog\MessageHandler\SendWelcomeMessageHandler` |
| `make:agent Sync -m vendor.blog` | 25.900 | `…\Infrastructure\Agent\SyncAgent` |
| `make:component my:post.list --no-module` | ≤ 25.100.500 | `/local/components/my/post.list/` |

- In `make:eventhandler`/`make:messagehandler` the argument is the **event/message** name and `-m` is the **handler** module.
- Namespace options (26.800 generators except `make:tablet`, `make:component`, `make:module`): `-P/--prefix V2` → `…\V2\Public\Service\…`; `-C/--context Post` → `…\Public\Service\Post\…`; `--no-psr4` → lower-case paths.
- `make:tablet` keeps its old options: `--namespace='Vendor\Blog\Model'` (e.g. for a `Model` layout), `--root`, `--show` (print only), `--no-psr4`.
- One-word module ids map to `Bitrix\X` and the generator writes into `/bitrix/modules/x/lib/` — move the file if your module lives in `/local/modules`.
- Generated code is a stub: events get no params, handlers lack the `use` for the event class, controllers need the module `controllers` section (`bitrix-controllers`).

## Before main 25.900 (checked on 25.100.500)

Only `make:controller`, `make:tablet`, `make:component`, `orm:annotate`, `dev:*`, `update:*`, `messenger:consume` exist. `make:controller` there: `make:controller Post vendor.blog [--namespace=…] [--root] [--show]` → `Vendor\Blog\Controller\Post`; no `-m`, `--actions`, `--alias`, `-P`, `-C`. Anything else: write the class by hand.

## `make:component`

Name `namespace:component` (`my:post.list`; no prefix → `bitrix`). With `--module=vendor.blog` (default: part before the first dot) it goes to `<root>/modules/<module>/install/components/<ns>/`; `--no-module` → `<root>/components/<ns>/`; `<root>` is `/local` for a non-`bitrix` namespace or with `--local`, else `/bitrix`. `--root`, `--show` as above.

## Kernel commands

- `orm:annotate [output] [-m main,vendor.blog] [-c/--clean] [--inside]` — PHPDoc for ORM classes (default file `bitrix/modules/orm_annotations.php`; `--inside` writes into the module, one module only).
- `messenger:consume [queue ...] [-t/--time-limit N] [--sleep N]` — `bitrix-background-jobs`.
- `update:modules [-m list] [-i versions.json]`, `update:versions <file.json>`, `update:languages [-l codes]` — marketplace/kernel updates.
- `dev:module-skeleton <module> [dir]` — empty `lib/` layer dirs; `dev:locator-codes <module> [code] [--show]` — `.phpstorm.meta.php` for `ServiceLocator::get()` codes from module `services`.
- `translate:index` — only with the `translate` module installed.
