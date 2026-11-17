# Генераторы и команды ядра

## Наличие и результат `make:*` (main 26.1000)

`<Vendor>\<Module>` — пространство имён из `-m vendor.module`. Пути: `/local/modules/vendor.module/lib/<хвост пространства имён>/`.

| Команда | Since main | Создаёт |
| --- | --- | --- |
| `make:module vendor.module [--name --description --module-version]` | 25.900 | Каркас установщика в `/local/modules/vendor.module/` (`bitrix-modules`) |
| `make:controller Post -m vendor.blog [--actions=crud\|list,get] [--alias=api]` | 25.900 (новый синтаксис) | `…\Infrastructure\Controller\Post` |
| `make:tablet b_vendor_post vendor.blog [--namespace=…]` | ≤ 25.100.500 | `…\Tablet\BVendorPostTable`; поля берутся из **существующей** таблицы БД |
| `make:entity Post -m vendor.blog [--fields=title,body]` | 25.900 | `…\Internals\Entity\Post` |
| `make:service Post -m vendor.blog` | 25.900 | `…\Public\Service\PostService` |
| `make:request CreatePost -m vendor.blog [--fields=…]` | 25.900 | `…\Infrastructure\Controller\Request\CreatePostRequest` |
| `make:event PostCreated -m vendor.blog` | 25.900 | `…\Public\Event\PostCreatedEvent` |
| `make:eventhandler PostCreated --event-module=vendor.blog -m vendor.notify` | 25.900 | `Vendor\Notify\Internals\Integration\Vendor\Blog\EventHandler\PostCreatedEventHandler` |
| `make:message SendWelcome -m vendor.blog` | 25.900 | `…\Public\Message\SendWelcomeMessage` |
| `make:messagehandler SendWelcome --message-module=vendor.blog -m vendor.blog` | 25.900 | `…\Internals\Integration\Vendor\Blog\MessageHandler\SendWelcomeMessageHandler` |
| `make:agent Sync -m vendor.blog` | 25.900 | `…\Infrastructure\Agent\SyncAgent` |
| `make:component my:post.list --no-module` | ≤ 25.100.500 | `/local/components/my/post.list/` |

- В `make:eventhandler`/`make:messagehandler` аргумент — имя **события/сообщения**, а `-m` — модуль **обработчика**.
- Параметры пространства имён (генераторы 26.800, кроме `make:tablet`, `make:component`, `make:module`): `-P/--prefix V2` → `…\V2\Public\Service\…`; `-C/--context Post` → `…\Public\Service\Post\…`; `--no-psr4` → пути в нижнем регистре.
- У `make:tablet` прежние параметры: `--namespace='Vendor\Blog\Model'` (например, для структуры `Model`), `--root`, `--show` (только показать), `--no-psr4`.
- Однословный ID модуля соответствует `Bitrix\X`, и генератор пишет в `/bitrix/modules/x/lib/`. Перенеси файл, если свой модуль находится в `/local/modules`.
- Сгенерированный код — заготовка: у событий нет параметров, у обработчиков отсутствует `use` класса события, контроллерам нужна секция `controllers` модуля (`bitrix-controllers`).

## До main 25.900 (проверено на 25.100.500)

Есть только `make:controller`, `make:tablet`, `make:component`, `orm:annotate`, `dev:*`, `update:*`, `messenger:consume`. Старый вызов `make:controller Post vendor.blog [--namespace=…] [--root] [--show]` создаёт `Vendor\Blog\Controller\Post`; нет `-m`, `--actions`, `--alias`, `-P`, `-C`. Остальные классы пиши вручную.

## `make:component`

Имя `namespace:component` (`my:post.list`; без префикса — `bitrix`). С `--module=vendor.blog` (по умолчанию — часть до первой точки) результат в `<root>/modules/<module>/install/components/<ns>/`; с `--no-module` — в `<root>/components/<ns>/`. `<root>` — `/local` для пространства имён кроме `bitrix` или с `--local`, иначе `/bitrix`. Параметры `--root`, `--show` описаны выше.

## Команды ядра

- `orm:annotate [output] [-m main,vendor.blog] [-c/--clean] [--inside]` — PHPDoc классов ORM (по умолчанию `bitrix/modules/orm_annotations.php`; `--inside` пишет внутрь одного выбранного модуля).
- `messenger:consume [queue ...] [-t/--time-limit N] [--sleep N]` — `bitrix-background-jobs`.
- `update:modules [-m list] [-i versions.json]`, `update:versions <file.json>`, `update:languages [-l codes]` — обновления ядра и Marketplace.
- `dev:module-skeleton <module> [dir]` создаёт пустые каталоги по старой схеме в существующем `lib/`. Архитектуру нового модуля см. в `bitrix-modules/rules/architecture.md`.
- `dev:locator-codes <module> [code] [--show]` — `.phpstorm.meta.php` для кодов `ServiceLocator::get()` из секции `services` модуля.
- `translate:index` — только при установленном модуле `translate`.


## `main:*` и JSON (**Since main 26.900**)

Доступны `main:option:get|set|delete|list`, `main:module:list`, `main:agent:list|get`, `main:site:list`, `main:language:list`, `main:event-handler:list`, `main:feature:enable|disable|reset`, `main:cache:clear`, `main:managed-cache:clear`, `main:tagged-cache:clear`, `main:settings:*`. Точный набор и параметры проверяй через `list`/`help` установленного ядра. Команды записи меняют состояние; полный сброс кеша затрагивает весь сайт.

```bash
php bitrix/bitrix.php main:module:list --format=json -n
php bitrix/bitrix.php help main:cache:clear
```

`--format=json` возвращает `{success, data, meta, error: {code, message}}`; ошибки идут в stderr, успех — stdout. Проверяй и код выхода, и `success`. До 26.900 этих команд нет: используй свой CLI с D7 API и обычным выводом Symfony Console.

`main:settings:crypto-get` и `main:settings:smtp-get` выводят значения секций, включая секреты: не направляй их в общие логи/CI. `main:sql` и `main:php-eval` выключены без `BX_LOCAL_CLI_ALLOW_UNSAFE_COMMANDS === true`; не включай их ради обычной диагностики. PHP выполняется с полными правами процесса, без изоляции; `exit`/фатальная ошибка могут оборвать JSON-ответ.
