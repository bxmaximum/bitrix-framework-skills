# Подключение маршрутизации и маршрутов модулей

## Веб-сервер

Запросы к несуществующим файлам направляй в `routing_index.php`.

Apache (`.htaccess`, вместо стандартного правила `urlrewrite.php`):

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-l
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !/bitrix/routing_index.php$
RewriteRule ^(.*)$ /bitrix/routing_index.php [L]
```

Nginx: `try_files $uri $uri/ /bitrix/routing_index.php;`

## Секция `routing`

Только глобальные настройки. Секция `routing` модульного `.settings.php` не читается.

```php
'routing' => [
    'value' => [
        'config' => ['web.php'], // имена файлов; поиск в /local/routes/ и /bitrix/routes/
    ],
    'readonly' => true,
],
```

Размещай в `/local/.settings_extra.php`: дополнительный файл заменяет секции верхнего уровня целиком, а копия в `/local/` заменяет `/bitrix/.settings_extra.php`. `/local/.settings.php` целиком заменяет `/bitrix/.settings.php` (`bitrix-settings`).

Порядок загрузки (`Application::initializeRouter()`): для каждого имени `/local/routes/<name>` и `/bitrix/routes/<name>` (оба, если оба есть). Затем системные файлы из `/bitrix/routes/.settings.php` (**Since main 26.250\***) либо `/bitrix/routes/web_bitrix.php` на старом ядре. Каждый возвращает `callable(RoutingConfigurator $routes): void`. Каталог `/bitrix/routes/` принадлежит системе.

## Маршруты модуля

Храни их в модуле и подключай через `require` из `/local/routes/web.php`:

```php
<?php declare(strict_types=1);

use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $moduleRoutes = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/vendor.module/routes/web.php';
    if (is_file($moduleRoutes))
    {
        (require $moduleRoutes)($routes);
    }

    // маршруты проекта…
};
```

После изменения подключённого через `require` файла выполни `touch` для указанного в настройках файла (`/local/routes/web.php`): ключ кеша компиляции учитывает только mtime перечисленных файлов (`rules/matching-legacy.md`).
