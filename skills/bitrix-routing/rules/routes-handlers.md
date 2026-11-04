# Маршруты, обработчики, параметры и группы

## `web.php`

```php
<?php declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;
use Vendor\Module\Infrastructure\Controller\Post;

return function (RoutingConfigurator $routes): void {
    $routes->get('/api/posts/', [Post::class, 'list'])->name('post.list');
    $routes->get('/api/posts/{id}', [Post::class, 'get'])->where('id', '\d+')->name('post.get');
    $routes->post('/api/posts/', [Post::class, 'create']);
    $routes->put('/api/posts/{id}', [Post::class, 'update'])->where('id', '\d+');
    $routes->delete('/api/posts/{id}', [Post::class, 'delete'])->where('id', '\d+');
};
```

## HTTP-методы

- `get` (также отвечает на `HEAD`), `post`, `put`, `patch`, `delete`, `options`, `any`.
- Несколько методов: `$routes->any('/api/posts/{id}', $handler)->methods(['PUT', 'PATCH'])`.
- Метода `head()` нет (пример документации неверен), `$routes->match(...)` вызывает "Unknown method" у конфигуратора.

## Обработчики

- `[Controller::class, 'view']`: действие `Engine\Controller`; допустимо и `'viewAction'` (суффикс удаляется). Работают фильтры контроллера, автосборка и JSON-обёртка (`bitrix-controllers`).
- Замыкание: параметры из маршрута по имени плюс `Bitrix\Main\Routing\Route` и `Bitrix\Main\HttpRequest`. Пролог загружается до вызова. Фильтров и CSRF нет: замыкания — для публичных действий только чтения.
- Строка класса, реализующего `Engine\Contract\RoutableAction`.
- `new PublicPageController('/path/page.php')`: подключение старой страницы (`rules/public-pages.md`).

Возвращаемое значение замыкания:

- `HttpResponse` (включая `Engine\Response\*`) отправляется без изменений.
- `array` → `Engine\Response\Json` (без обёртки `status`/`data`).
- Прочее приводится к строке тела.
- `null` ничего не отправляет.

```php
use Bitrix\Main\Engine\Response\Render\Component;

$routes->get('/health', fn () => 'ok'); // строка тела ответа

// HTML-страница с компонентом внутри шаблона сайта (Since main 25.700)
$routes->get('/about/', fn () => new Component(
    'bitrix:main.include', '', ['AREA_FILE_SHOW' => 'file', 'PATH' => '/include/about.php'],
));
```

`Engine\Response\Component` — JSON-данные AJAX, не страница. Рендеринг страниц контроллера: `renderView()` / `renderComponent()`.

## Параметры

- `{name}` соответствует `[^/]+`. Ограничивай: `->where('id', '\d+')` или `->where(['section' => '[a-z]+', 'slug' => '[a-z0-9\-]+'])`. В шаблоне нельзя `#` (разделитель выражения).
- Необязательный параметр получает значение по умолчанию и может быть пустым: `$routes->get('/posts/{page}', $h)->default('page', 1)` совпадёт и с `/posts/`. Синтаксиса `{page?}` нет (имя соответствует `[a-z0-9_]+`).
- Значение `default()` для имени вне URI всё равно передаётся обработчику (и в `$_GET` у `PublicPageController`).
- Контроллер получает параметры аргументами действия; не читай `getCurrentRoute()` в других местах.

## Имена и URL

```php
$url = (string)\Bitrix\Main\Application::getInstance()->getRouter()->route('post.get', ['id' => 42]);
```

Имена должны быть уникальны среди всех файлов маршрутов.

## Группы

`group()` принимает только замыкание; `prefix`/`name`/`where`/`default`/`domain` задай перед ним в цепочке:

```php
$routes->prefix('api')->name('api.')->group(function (RoutingConfigurator $routes) {
    $routes->get('/posts/', [Post::class, 'list'])->name('post.list'); // /api/posts/, api.post.list

    $routes->prefix('admin')->name('admin.')->group(function (RoutingConfigurator $routes) {
        $routes->get('/stats/', [Admin::class, 'stats'])->name('stats'); // /api/admin/stats/, api.admin.stats
    });
});
```

- Формат Laravel `group(['prefix' => …], fn)` не поддерживается.
- `prefix()` принимает путь без начального слеша (`api`, `ltrim($siteDir, '/')`).
- Узкие маршруты регистрируй перед универсальным `{any}` с `->where('any', '.*')`.
- Конечный путь без параметра лучше завершать слешем (`/settings/`): сегмент с точкой веб-сервер может принять за статический файл.
