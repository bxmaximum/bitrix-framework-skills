# Файлы, имена действий, автосборка

## Файлы и имена действий

`.settings.php` модуля (`/local/modules/vendor.module/.settings.php`):

```php
'controllers' => [
    'value' => [
        'defaultNamespace' => '\\Vendor\\Module\\Infrastructure\\Controller',
        'namespaces' => [
            '\\Vendor\\Module\\Infrastructure\\Controller\\Web' => 'web', // сегмент псевдонима
        ],
        'restIntegration' => ['enabled' => true], // только для области REST
    ],
    'readonly' => true,
],
```

- `lib/Infrastructure/Controller/Post.php` → действие `vendor:module.Post.create` (`Post::createAction`), URL `/bitrix/services/main/ajax.php?action=vendor:module.Post.create`.
- `Web\Post::getAction` → `vendor:module.web.Post.get`. Суффикс `Controller` неизменен.
- Если сегмент контроллера начинается со строчной буквы, разрешитель приводит весь путь пространства имён по умолчанию к нижнему регистру: загрузчик ищет `lib/infrastructure/controller/post.php`. Файлы PascalCase находятся на macOS, но на Linux дают "action not found". Ядро: `Engine/Resolver.php`, `buildControllerClassName()`, `Loader::autoLoad()`.

## Минимальный контроллер

Движок создаёт контроллер только с `Request` (`ControllerBuilder`). DI конструктора нет.

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
    #[DisablePrefilters([ActionFilter\Csrf::class])] // только чтение; Authentication сохраняется
    public function getAction(int $id, PostService $posts): ?array
    {
        $post = $posts->find($id);
        if ($post === null)
        {
            $this->addError(new Error('Запись не найдена', 'POST_NOT_FOUND'));
            return null;
        }

        return ['post' => $post];
    }

    #[HttpMethod([ActionFilter\HttpMethod::METHOD_POST])] // стандартные Authentication + Csrf сохраняются
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

Атрибуты требуют main 26.250\*; для старого ядра настройка через `configureActions()` в `rules/filters.md`. `JsonController` — это `Controller` с предварительным фильтром `ContentType([JSON])`.

Порядок: `__construct(Request)` → `init()` (строит конфигурацию фильтров; сначала вызывай `parent::init()`) → `processBeforeAction()` → предварительные фильтры → действие → последующие фильтры, `processAfterAction()` → ответ. `processBeforeAction()` выполняется до `Authentication`/`Csrf`: не размещай там побочные эффекты и логику, зависящую от прав. Компоненты (`class.php`, `Controllerable`) вызывают `onPrepareComponentParams()` для AJAX, но не `executeComponent()` (`bitrix-components`).

## Параметры действий (`Engine\AutoWire\Binder`)

Для типа-класса выбирается первое совпадение:

1. `getPrimaryAutoWiredParameter()` / `getAutoWiredParameters()` контроллера и глобальные фабрики: `PageNavigation` (ID `nav`), `JsonPayload`, `CurrentUser`. Также подклассы.
2. Перечисление со значениями: `tryFrom()` от значения запроса с именем параметра.
3. Значение параметра по умолчанию.
4. `ServiceLocator::get(FQCN)`, но только при `class_exists()`. Тип-интерфейс не разрешается даже после регистрации. Указывай конкретный класс.

Скаляры и `array` по имени: форма POST, затем строка запроса (при заголовке `BX-AJAX-QB` — JSON из `bx_data`), затем значение по умолчанию. Не из `$_FILES` и не из тела JSON, кроме случая с фильтром `ContentType([JSON])` (`JsonController`): он добавляет верхние ключи тела последним источником. Иначе принимай `JsonPayload $payload` и читай `$payload->getData()`. Пропуск/неверный тип → `BinderArgumentException` и ошибка ответа. Объединённые типы запрещены.

`HttpRequest` не внедряется: `$this->getRequest()`. Для `Session` контейнер создаёт новый объект; сессию приложения бери через `Application::getInstance()->getSession()`. `HttpRequest` и `Route` внедряются только в замыкания маршрутов (`bitrix-routing`). Проверяемый DTO запроса — `ValidationParameter` (`bitrix-validation`).

### Загрузка сущности по ID

Привязку ORM объяви сам: второй аргумент замыкания получает `<paramName>Id` из запроса:

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

public function viewAction(Post $post): array { /* ключ запроса: postId */ }
```

`null` из замыкания допустим только для nullable-параметра. Проверяй права на объект, тип/владельца бери из записи, не запроса (`bitrix-security`).

## PageNavigation

`PageNavigation $nav` строится из ключа запроса `nav`: размер 1–50 (при другом `size` — 20), без числа записей; `page-all` игнорируется, номер страницы не ограничивается. Передай `$nav->getOffset()`/`getLimit()` сервису, затем вызови `$nav->setRecordCount($total)`. Для другого ID, размера, двух списков создавай `new PageNavigation('my-nav')` самостоятельно. Не указывай тип `ReversePageNavigation`/`AdminPageNavigation`: глобальная фабрика возвращает базовый класс, что вызовет `TypeError`.

## Вызов из браузера

```js
BX.ajax.runAction('vendor:module.Post.list', {
    data: { sectionId: 5 },
    navigation: { page: 3, size: 20 }, // → ?nav=page-3-size-20
}).then((response) => response.data, (response) => response.errors);
```
