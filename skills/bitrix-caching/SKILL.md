---
name: bitrix-caching
description: 'Bitrix caching — Data\Cache, ManagedCache, TaggedCache, ORM query cache, component result cache, cache engine config (files, Redis, Memcached). Use when caching data or HTML, choosing TTL and keys, or invalidating by tags.'
---

# Caching

Baseline: main 23.0+ · Verified: main 26.800.0

| Level | API | Invalidation |
| --- | --- | --- |
| Data cache | `Data\Cache` (`initCache`/`startDataCache`) | TTL, `cleanDir($dir)`, tags |
| Managed cache | `Application::getInstance()->getManagedCache()` | `clean()` / `cleanDir()` |
| Tags | `Application::getInstance()->getTaggedCache()` | `clearByTag($tag)` |
| ORM query cache | `setCacheTtl()` / `'cache' => ['ttl' => …]` | automatic on ORM writes (`bitrix-orm`) |
| Component result | `$this->startResultCache()` | `CACHE_TIME`, tags, `clearResultCache()` |
| Whole page | Composite | `bitrix-performance` |

## Engine config (`.settings.php`)

```php
'cache' => [
    'value' => [
        'type' => 'redis',                               // files | redis | memcached | memcache | apcu
        'redis' => ['host' => '127.0.0.1', 'port' => 6379],
        'serializer' => \Redis::SERIALIZER_IGBINARY,
        'sid' => 'site1',                                // key prefix: unique per site copy on a shared server
    ],
    'readonly' => false,
],
'cache_flags' => [                                       // separate top-level section
    'value' => [
        'config_options' => 3600,                        // b_option cache TTL
        'vendor_module_post_max_ttl' => 600,             // ORM TTL clamp per table (0 disables)
    ],
],
```

- Custom engine: `'type' => ['class_name' => MyEngine::class, 'extension' => 'redis']`. Several servers: `'servers' => [['host' => …, 'port' => …]]`.
- An unknown `type` string (typo, wrong case) silently means "no cache", without a warning. A missing custom class or an unavailable engine (no extension, server down) falls back to "no cache" with only an `E_USER_WARNING`.

## Data cache + tags

```php
use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;

$cache = Cache::createInstance();
$cacheDir = '/vendor_blog/posts';
$cacheId = 'list_' . md5(serialize([$filter, LANGUAGE_ID]));

if ($cache->initCache(3600, $cacheId, $cacheDir))
{
    $data = $cache->getVars();
}
elseif ($cache->startDataCache())
{
    $tagged = Application::getInstance()->getTaggedCache();
    $tagged->startTagCache($cacheDir);                   // same dir as the data cache
    $data = PostTable::query()->setSelect(['ID', 'TITLE'])->setFilter($filter)->fetchAll();

    if ($data === [])
    {
        $tagged->abortTagCache();
        $cache->abortDataCache();
    }
    else
    {
        $tagged->registerTag('vendor_blog_posts');
        $tagged->endTagCache();
        $cache->endDataCache($data);
    }
}

Application::getInstance()->getTaggedCache()->clearByTag('vendor_blog_posts'); // in the write path / ORM OnAfter* handler
```

- `clearByTag()` deletes every cache **dir** registered under the tag, so tag data caches with their own `$cacheDir`.
- Key = everything that changes the result (filter, language, site, user groups). Never read `$_SESSION`/`$USER` inside the cached block without putting it in the key.
- Tags are your own names. There are no automatic `ORM_<TABLE>` tags; ORM cache lives in managed-cache dirs `orm_<table>` and is cleared by `XxxTable::cleanCache()`.

## Managed cache

```php
$managed = Application::getInstance()->getManagedCache();
if ($managed->read(86400, 'rates', 'vendor_rates'))
{
    $rates = $managed->get('rates');
}
else
{
    $rates = $this->loadRates();
    $managed->set('rates', $rates);          // setImmediate() writes now, set() at the end of the hit
}
$managed->cleanDir('vendor_rates');
```

`set()`/`setImmediate()` are no-ops for an id that was not `read()` first.

## Component cache

```php
if ($this->startResultCache(false, [$userGroupsKey]))  // CACHE_TIME from params; extra key parts
{
    $this->arResult['ITEMS'] = $this->loadItems();
    if (!$this->arResult['ITEMS'])
    {
        $this->abortResultCache();
    }
    else
    {
        Application::getInstance()->getTaggedCache()->registerTag('vendor_blog_posts');
    }
    $this->setResultCacheKeys(['TITLE']);              // arResult keys kept for component_epilog.php
    $this->includeComponentTemplate();
}
```

- Params: `CACHE_TYPE` (`A` follows the global switch, `Y`, `N`), `CACHE_TIME`. The key already includes site, language, template, all params and the user TZ offset; `CACHE_GROUPS` is only a convention: add user groups to the extra key yourself.
- The component opens a tag scope for its cache path only when `BX_COMP_MANAGED_CACHE` is defined.

## Checklist

- [ ] Cache key covers every input; empty/error results call `abort*Cache()`.
- [ ] Each tag is cleared in the code path that changes the data.
- [ ] Tag scope uses the same dir as the data cache.
- [ ] ORM cache cleared via `cleanCache()` after raw SQL writes.
- [ ] Production uses Redis/Memcached, `sid` unique per site copy.
