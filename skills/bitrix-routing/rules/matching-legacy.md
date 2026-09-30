# Matching, cache, site-guard, urlrewrite.php

## Matching

- All route files feed one `Router`; the first route whose regex and method match wins. Order inside a file matters. Don't rely on order across files.
- Entry `/bitrix/routing_index.php` (`rules/setup.md`): routes first, then `urlrewrite.php` rules as the fallback. Stock entry `/bitrix/urlrewrite.php`: its rules first, routes after (**Since main 26.250\***; older kernels reach no routes from it). A conflicting rule there shadows the route.
- `->middleware()` callables run before the prolog and before controller filters. Not for auth, CSRF or rights.

## Compile cache

Compiled regexes are cached for a year in cache dir `routing` (`CompileCache`), keyed by the mtimes of the files listed in `routing.config`. Compiled data is stored by route position. After editing a `require`d module route file, `touch /local/routes/web.php` or clear the cache. Otherwise routes match stale regexes.

## Site-guard (multisite)

Routes register for every site unless the file returns early:

```php
use Bitrix\Main\Context;
use Bitrix\Main\Routing\RoutingConfigurator;
use Bitrix\Main\SiteTable;

return function (RoutingConfigurator $routes): void {
    // routes for all sites (e.g. .well-known) go above the guard

    $request = Context::getCurrent()->getRequest();
    $site = SiteTable::getByDomain($request->getHttpHost(), $request->getRequestUri()); // array, not a Result
    if (($site['LID'] ?? '') !== 's1')
    {
        return;
    }

    // s1 routes…
};
```

`getByDomain()` falls back to the default site. The guard selects routes; it doesn't authorize.

## Migrating from `urlrewrite.php`

1. New URLs go to `/local/routes/`, never to `urlrewrite.php`. Legacy SEF component rules may stay.
2. Rule → route:

    ```php
    // ['CONDITION' => '#^/catalog/section/(\d+)/?$#', 'RULE' => 'SECTION_ID=$1', 'PATH' => '/catalog/section.php']
    $routes->get('/catalog/section/{id}/', [Catalog::class, 'section'])->where('id', '\d+');
    ```

3. Remove the old rule: `\Bitrix\Main\UrlRewriter::delete('s1', ['CONDITION' => '#^/catalog/section/(\d+)/?$#'])`. `UrlRewriter::reindexAll()` rebuilds rules from pages with SEF components, so it re-adds rules while such a page still exists.
