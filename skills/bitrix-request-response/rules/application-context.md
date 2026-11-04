# Приложение и контекст

## Приложение

Одиночка на время хита: настраивает ядро и предоставляет общие сервисы.

```php
use Bitrix\Main\Application;

$app = Application::getInstance();

$app->getContext();           // текущий контекст
$app->getManagedCache();      // управляемый кеш
$app->getTaggedCache();       // тегированный кеш
$app->getSession();           // объект сессии (см. bitrix-sessions)
$app->getConnection();        // основное соединение БД
$app->getConnection('log');   // именованное соединение из connections
$app->getKernelSession();     // сессия ядра
$app->addBackgroundJob(fn () => /* ... */);     // см. bitrix-background-jobs
```

Наследники: `HttpApplication` (HTTP-хит), `CliApplication` (CLI через `bitrix.php`).

## Контекст

Окружение одного запроса: `Request`, `Response`, `Server`, язык, `Culture`, сайт.

```php
use Bitrix\Main\Context;

$ctx = Context::getCurrent();

$ctx->getRequest();    // HttpRequest
$ctx->getResponse();   // HttpResponse
$ctx->getServer();     // Server — обёртка над $_SERVER
$ctx->getCulture();    // региональные форматы
$ctx->getLanguage();   // 'ru'
$ctx->getSite();       // 's1'
$ctx->getEnvironment();
```

`Context::getCurrent()` — короткая форма `Application::getInstance()->getContext()`.
