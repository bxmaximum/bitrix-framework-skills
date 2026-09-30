# Routes, handlers, params, groups

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

## Verbs

- `get` (also answers `HEAD`), `post`, `put`, `patch`, `delete`, `options`, `any`.
- Several verbs: `$routes->any('/api/posts/{id}', $handler)->methods(['PUT', 'PATCH'])`.
- There is no `head()` (the docs example is wrong), and `$routes->match(...)` throws "Unknown method" on the configurator.

## Handlers

- `[Controller::class, 'view']`: `Engine\Controller` action; `'viewAction'` works too (suffix stripped). Runs with controller filters, autowire and the JSON envelope (`bitrix-controllers`).
- Closure: params autowired by name from the route, plus `Bitrix\Main\Routing\Route` and `Bitrix\Main\HttpRequest`. The prolog is loaded before the call. No filters and no CSRF: closures are for public read-only endpoints.
- Class string implementing `Engine\Contract\RoutableAction`.
- `new PublicPageController('/path/page.php')`: legacy include (`rules/public-pages.md`).

Closure return value:

- `HttpResponse` (incl. `Engine\Response\*`) → sent as is.
- `array` → `Engine\Response\Json` (no `status`/`data` envelope).
- anything else → cast to string body.
- `null` → nothing sent.

```php
use Bitrix\Main\Engine\Response\Render\Component;

$routes->get('/health', fn () => 'ok'); // string body

// HTML page with a component inside the site template (Since main 25.700)
$routes->get('/about/', fn () => new Component(
    'bitrix:main.include', '', ['AREA_FILE_SHOW' => 'file', 'PATH' => '/include/about.php'],
));
```

`Engine\Response\Component` is the AJAX JSON payload, not a page. Page rendering in controllers: `renderView()` / `renderComponent()`.

## Parameters

- `{name}` matches `[^/]+`. Constrain it: `->where('id', '\d+')` or `->where(['section' => '[a-z]+', 'slug' => '[a-z0-9\-]+'])`. No `#` in patterns (the regex delimiter).
- Optional: give the param a default, then it may be empty: `$routes->get('/posts/{page}', $h)->default('page', 1)` matches `/posts/` too. There is no `{page?}` syntax (the name regex is `[a-z0-9_]+`).
- A `default()` for a name not in the URI is still passed to the handler (and to `$_GET` for `PublicPageController`).
- Controllers receive params as action arguments; don't read `getCurrentRoute()` elsewhere.

## Names and URLs

```php
$url = (string)\Bitrix\Main\Application::getInstance()->getRouter()->route('post.get', ['id' => 42]);
```

Names must be unique across all route files.

## Groups

`group()` takes only a closure; set `prefix`/`name`/`where`/`default`/`domain` in the chain before it:

```php
$routes->prefix('api')->name('api.')->group(function (RoutingConfigurator $routes) {
    $routes->get('/posts/', [Post::class, 'list'])->name('post.list'); // /api/posts/, api.post.list

    $routes->prefix('admin')->name('admin.')->group(function (RoutingConfigurator $routes) {
        $routes->get('/stats/', [Admin::class, 'stats'])->name('stats'); // /api/admin/stats/, api.admin.stats
    });
});
```

- No Laravel-style `group(['prefix' => …], fn)`.
- `prefix()` takes a path without a leading slash (`api`, `ltrim($siteDir, '/')`).
- Register narrow routes before a catch-all `{any}` + `->where('any', '.*')`.
- Leaf paths without a param: prefer a trailing slash (`/settings/`). A dotted last segment may be treated as a static file by the web server.
