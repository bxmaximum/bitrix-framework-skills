# Public pages: controller or PublicPageController

| Page | Use |
| --- | --- |
| Layout/markup owned by developers | route → controller → `renderView()` |
| One component, params in code | route → controller → `renderComponent()` |
| Content managers edit the file or component params | keep the physical page, or `PublicPageController` |
| Many legacy pages, gradual migration | `PublicPageController` for now |

## Page on a controller (**Since main 25.700**)

```php
// /local/routes/web.php
$routes->get('/blog/', [\Vendor\Blog\Infrastructure\Controller\Web\Blog::class, 'index']);
```

```php
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\DisablePrefilters;
use Bitrix\Main\Engine\ActionFilter\Attribute\Rule\HttpMethod;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpResponse;

final class Blog extends Controller
{
    #[HttpMethod([ActionFilter\HttpMethod::METHOD_GET])]
    #[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])] // public read-only
    public function indexAction(): HttpResponse
    {
        return $this->renderView('blog/index', ['title' => 'Blog']);
        // or: return $this->renderComponent('bitrix:news.list', '', ['IBLOCK_ID' => 5]);
    }
}
```

- Routed controllers run in `SCOPE_AJAX` with the default filters: guests get a 401 JSON error, and a plain link fails `Csrf` for everyone. Drop both for public read-only pages; keep `HttpMethod`; never drop `Csrf` on actions that change data.
- Attributes need main 26.250\*; on 25.700–26.2xx use `configureActions()` with `'-prefilters' => [ActionFilter\Authentication::class, ActionFilter\Csrf::class]`.
- View path rules (`/local/modules/<module>/views/blog/index.php`, title via `$APPLICATION->SetTitle()`): `bitrix-controllers` → `rules/errors-response.md`.
- Delete the old `/blog/index.php` once the route works: a physical file is served before the route.
- Public edit mode sees `/bitrix/routing_index.php` as the page file (`REAL_FILE_PATH`): never edit it. Pages that content managers edit stay physical.

## `PublicPageController` (legacy bridge)

Includes an existing public PHP page. No new logic behind it.

```php
use Bitrix\Main\Routing\Controllers\PublicPageController;
use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $siteDir = '/';                       // path under DOCUMENT_ROOT
    $sitePrefix = ltrim($siteDir, '/');   // for prefix()

    $routes->prefix($sitePrefix . 'docs')->group(function (RoutingConfigurator $routes) use ($siteDir) {
        $routes->get('item/{id}/', new PublicPageController($siteDir . 'docs/item.php'))
            ->where('id', '[0-9]+')
            ->default('download', '0');

        $routes->any('{any}', new PublicPageController($siteDir . 'docs/index.php'))
            ->where('any', '.*');
    });
};
```

- It `include`s the file and `die()`s. It runs before the prolog and without filters, and middlewares run first: the page itself checks input, rights and CSRF.
- Route params and `default()` values are copied into `$_GET`/`$_REQUEST`.
