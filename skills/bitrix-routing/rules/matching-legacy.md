# Выбор маршрута, кеш, выбор сайта и urlrewrite.php

## Выбор маршрута

- Все файлы заполняют один `Router`; побеждает первый маршрут с подходящими регулярным выражением и HTTP-методом. Порядок внутри файла важен; на порядок между файлами не полагайся.
- Вход `/bitrix/routing_index.php` (`rules/setup.md`): сначала маршруты, затем запасной `urlrewrite.php`. Стандартный вход `/bitrix/urlrewrite.php`: сначала его правила, затем маршруты (**Since main 26.250\***; раньше маршруты оттуда недоступны). Конфликтующее правило скрывает маршрут.
- Функции `->middleware()` выполняются до пролога и фильтров контроллера. Они не подходят для авторизации, CSRF и прав.

## Кеш компиляции

Скомпилированные выражения кешируются на год в каталоге `routing` (`CompileCache`); ключ включает mtime файлов из `routing.config`. Данные хранятся по позиции маршрута. После изменения подключённого через `require` файла модуля выполни `touch /local/routes/web.php` или очисти кеш, иначе используются старые выражения.

## Выбор сайта при многосайтовости

Маршруты регистрируются для каждого сайта, если файл не завершится раньше:

```php
use Bitrix\Main\Context;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\SiteTable;

return function (RoutingConfigurator $routes): void {
    // общие маршруты (например .well-known) размещай перед проверкой сайта

    $request = Context::getCurrent()->getRequest();
    $site = SiteTable::getByDomain($request->getHttpHost(), $request->getRequestUri()); // массив, не Result
    if (($site['LID'] ?? '') !== 's1')
    {
        return;
    }

    // маршруты s1…
};
```

`getByDomain()` при отсутствии совпадения возвращает сайт по умолчанию. Проверка выбирает маршруты, но не авторизует.

## Перенос из `urlrewrite.php`

1. Новые URL добавляй в `/local/routes/`, не в `urlrewrite.php`. Старые правила ЧПУ компонентов можно оставить.
2. Замени правило маршрутом:

    ```php
    // ['CONDITION' => '#^/catalog/section/(\d+)/?$#', 'RULE' => 'SECTION_ID=$1', 'PATH' => '/catalog/section.php']
    $routes->get('/catalog/section/{id}/', [Catalog::class, 'section'])->where('id', '\d+');
    ```

3. Удали старое правило: `\Bitrix\Main\UrlRewriter::delete('s1', ['CONDITION' => '#^/catalog/section/(\d+)/?$#'])`. `UrlRewriter::reindexAll()` восстанавливает правила страниц с ЧПУ-компонентами и добавит правило снова, пока такая страница существует.
