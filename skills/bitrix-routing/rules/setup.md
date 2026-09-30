# Enable routing, wire module routes

## Web server

Send requests for non-existent files to `routing_index.php`.

Apache (`.htaccess`, replaces the stock `urlrewrite.php` rule):

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-l
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !/bitrix/routing_index.php$
RewriteRule ^(.*)$ /bitrix/routing_index.php [L]
```

Nginx: `try_files $uri $uri/ /bitrix/routing_index.php;`

## `routing` section

Global config only. Module `.settings.php` `routing` is not read.

```php
'routing' => [
    'value' => [
        'config' => ['web.php'], // basenames, looked up in /local/routes/ and /bitrix/routes/
    ],
    'readonly' => true,
],
```

Put it into `/local/.settings_extra.php`: the extra file replaces whole top-level sections, and a `/local/` copy replaces `/bitrix/.settings_extra.php`. `/local/.settings.php` replaces `/bitrix/.settings.php` entirely (`bitrix-settings`).

Load order (`Application::initializeRouter()`): for each basename, `/local/routes/<name>` and `/bitrix/routes/<name>` (both if both exist). Then system files: those listed in `/bitrix/routes/.settings.php` (**Since main 26.250\***), or `/bitrix/routes/web_bitrix.php` on older kernels. Each file returns `callable(RoutingConfigurator $routes): void`. `/bitrix/routes/` belongs to the system.

## Module routes

Keep them in the module and `require` them from `/local/routes/web.php`:

```php
<?php declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $moduleRoutes = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/vendor.module/routes/web.php';
    if (is_file($moduleRoutes))
    {
        (require $moduleRoutes)($routes);
    }

    // project routes…
};
```

After editing a `require`d file, `touch` the listed file (`/local/routes/web.php`): the compiled-route cache key includes only the mtimes of the listed files (`rules/matching-legacy.md`).
