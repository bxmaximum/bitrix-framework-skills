---
name: bitrix-service-locator
description: 'Используй для DI Битрикса: ServiceLocator, services в .settings.php, привязки интерфейсов, фабрики и автосборка. Переопределение сервисов, контекст вызова и версия ядра.'
---

# ServiceLocator и внедрение зависимостей

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

Границы DI задаёт `bitrix-framework`: сервисы и получатели Messenger поддерживают конструктор; контроллеры — параметры действий; CLI — конструктор **Since main 26.900**. Классы предметной области не обращаются к контейнеру.

## Как разрешается `get($id)` (**Since main 26.250\***)

| Ключ | Результат |
| --- | --- |
| Уже создан или передан в `addInstance()` | Тот же экземпляр |
| Не зарегистрирован, класс существует | Автосборка: параметр конструктора = `get(<его тип>)`; при наличии значения по умолчанию берётся оно |
| Зарегистрирован интерфейс / абстрактный класс | `className` → реализация с **автосборкой** (`constructorParams` игнорируется); замыкание `constructor` вызывается с аргументами `constructorParams` |
| Зарегистрирован конкретный класс или строковый ID | `className` → `new $class(...array_values(constructorParams))`, **без автосборки**; замыкание `constructor` вызывается без аргументов |

- Экземпляры кешируются на время процесса, кроме записей с `'singleton' => false`.
- Цикл вызывает `CircularDependencyException`. Нетипизированный, объединённый или скалярный параметр без значения по умолчанию вызывает `ServiceNotFoundException`.
- До 26.250 зарегистрированный ключ создаётся через `new $class(...constructorParams)` или `constructor()`. Незарегистрированные классы собираются, только если все параметры конструктора — конкретные классы (без интерфейсов; значения по умолчанию игнорируются). Параметра `singleton` нет. На таком ядре привязывай реализации с зависимостями через замыкание `constructor`.

## Регистрация

Секция `services` в `/local/modules/vendor.module/.settings.php` регистрируется при `Loader::includeModule()`; также доступна в глобальных настройках:

```php
<?php
return [
    'services' => [
        'value' => [
            // интерфейс → реализация (автосборка с 26.250*)
            \Vendor\Blog\Domain\PostRepositoryInterface::class => [
                'className' => \Vendor\Blog\Infrastructure\OrmPostRepository::class,
            ],
            // скалярные аргументы → фабрика
            \Vendor\Blog\Infrastructure\TelegramClient::class => [
                'constructor' => static fn () => new \Vendor\Blog\Infrastructure\TelegramClient(
                    (string)getenv('TELEGRAM_BOT_TOKEN'),
                ),
            ],
            // позиционные аргументы; замыкание вычисляется при первом get()
            'vendor.blog.mailer' => [
                'className' => \Vendor\Blog\Infrastructure\Mailer::class,
                'constructorParams' => static fn () => ['noreply@example.com'],
            ],
        ],
        'readonly' => true,
    ],
];
```

- Не регистрируй конкретный класс, которому нужны только другие классы. `get(PostService::class)` соберёт зависимости, а один `[ 'className' => PostService::class ]` приведёт к `new PostService()` → `ArgumentCountError`.
- Предпочитай ключи FQCN. Строковые ID (`main.validation.service`) — соглашение ядра.
- Ядро регистрирует `Application`, `Routing\Router`, `DB\Connection`, `Data\Cache`, `Data\ManagedCache`, `Data\TaggedCache`, `EventManager`, `Data\ConnectionPool`, `\CUserTypeManager` (**Since main 26.250\***) и `Data\Storage\PersistentStorageInterface` (**Since main 25.1100**, `bitrix-storage`). Передавай их в конструктор вместо вызовов `Application::getInstance()->…`.

## Переопределения

- Побеждает первая регистрация: `registerByModuleSettings()` пропускает существующие ключи. Последующий модуль не переопределяет предыдущий.
- Переопределяй привязку модуля в глобальной секции `services` файла `/local/.settings_extra.php` (регистрируется при старте до модулей) или во время выполнения (`init.php`, тесты): `ServiceLocator::getInstance()->addInstance($id, $object)` / `addInstanceLazy($id, ['className' => …])`.

## Получение сервисов

- Сервисы: параметры конструктора.
- Действия контроллеров: конкретный класс в типе параметра. Интерфейсы в параметрах действий не разрешаются (`bitrix-controllers`).
- Получатели Messenger создаются через `get(FQCN)` с автосборкой. Команды CLI поддерживают её **Since main 26.900**; на старом ядре получай сервис в `execute()`. Обработчики событий получают сервис внутри обработчика.
- `has($id)` возвращает true только для зарегистрированных или созданных ID, а не для любых классов, доступных автосборке.

## Правила

- Сервисы — одиночки на время процесса: не храни в свойствах состояние запроса. Долгоживущие потребители сохраняют экземпляры между сообщениями.
- Не создавай `new Service()` в контроллере/команде, если контейнер может его собрать. В предметной области вызовов контейнера нет.
- Конфигурация передаётся типизированным объектом или скалярами через фабрику, а не сервисом с произвольным массивом.
- Флаги возможностей: `Bitrix\Main\Config\Feature` (**Since main 26.700**, `bitrix-storage`), а не самодельный сервис флагов.
