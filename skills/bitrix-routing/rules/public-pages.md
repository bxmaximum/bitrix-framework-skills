# Публичные страницы: контроллер или PublicPageController

| Страница | Использовать |
| --- | --- |
| Разработчик управляет структурой и разметкой | Маршрут → контроллер → `renderView()` |
| Один компонент с параметрами в коде | Маршрут → контроллер → `renderComponent()` |
| Контент-менеджер правит файл или параметры компонента | Физическая страница либо `PublicPageController` |
| Много старых страниц, постепенный перенос | Временно `PublicPageController` |

## Страница контроллера (**Since main 25.700**)

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
    #[DisablePrefilters([ActionFilter\Authentication::class, ActionFilter\Csrf::class])] // публичная страница только для чтения
    public function indexAction(): HttpResponse
    {
        return $this->renderView('blog/index', ['title' => 'Blog']);
        // либо: return $this->renderComponent('bitrix:news.list', '', ['IBLOCK_ID' => 5]);
    }
}
```

- Контроллеры маршрутов работают в `SCOPE_AJAX` со стандартными фильтрами: гость получает 401 с JSON-ошибкой, обычная ссылка не проходит `Csrf` у любого пользователя. Для публичной страницы только для чтения отключи оба фильтра, сохрани `HttpMethod`. Не отключай `Csrf` у изменяющих данные действий.
- Атрибуты требуют main 26.250\*; на 25.700–26.2xx используй `configureActions()` с `'-prefilters' => [ActionFilter\Authentication::class, ActionFilter\Csrf::class]`.
- Путь представления (`/local/modules/<module>/views/blog/index.php`, заголовок через `$APPLICATION->SetTitle()`): `bitrix-controllers` → `rules/errors-response.md`.
- Удали старую `/blog/index.php` после проверки маршрута: физический файл отдаётся раньше маршрута.
- Публичный режим правки видит страницей `/bitrix/routing_index.php` (`REAL_FILE_PATH`): не редактируй его. Редактируемые контент-менеджерами страницы оставляй физическими.

## `PublicPageController`: переход со старых страниц

Подключает существующую публичную PHP-страницу. Новую логику за ним не создавай.

```php
use Bitrix\Main\Routing\Controllers\PublicPageController;
use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $siteDir = '/';                       // путь внутри DOCUMENT_ROOT
    $sitePrefix = ltrim($siteDir, '/');   // для prefix()

    $routes->prefix($sitePrefix . 'docs')->group(function (RoutingConfigurator $routes) use ($siteDir) {
        $routes->get('item/{id}/', new PublicPageController($siteDir . 'docs/item.php'))
            ->where('id', '[0-9]+')
            ->default('download', '0');

        $routes->any('{any}', new PublicPageController($siteDir . 'docs/index.php'))
            ->where('any', '.*');
    });
};
```

- Подключает файл через `include` и вызывает `die()`. Работает до пролога без фильтров; middleware выполняются первыми. Страница сама проверяет ввод, права и CSRF.
- Параметры маршрута и значения `default()` копируются в `$_GET`/`$_REQUEST`.
