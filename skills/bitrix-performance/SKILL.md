---
name: bitrix-performance
description: 'Bitrix high-load tuning — Composite (dynamic areas, createFrame, StaticArea), query optimization, read replicas via the cluster module. Use when a page or query is slow or when enabling composite.'
---

# Performance

Baseline: main 23.0+ · Verified: main 26.800.0

Caching itself: `bitrix-caching`. SQL profiling: `bitrix-database` (SqlTracker). Sessions: `bitrix-sessions`.

## Composite

Static HTML of the page is served from the composite cache (NGINX can serve it directly); dynamic areas are fetched by a follow-up request. Enable in Settings → Composite site. Kernel: `Bitrix\Main\Composite\Engine`, `Page`, `StaticArea`, `BufferArea`.

In a component template (`$this` is `CBitrixComponentTemplate`):

```php
$this->setFrameMode(true);                 // template is composite-compatible

$frame = $this->createFrame()->begin('Loading…');  // stub shown in the cached HTML
echo htmlspecialcharsbx($arResult['USER_NAME']);   // dynamic content
$frame->end();
```

Long stub markup: `begin()` → dynamic content → `beginStub()` → stub HTML → `end()`.

Outside components (site template header, `init.php` helpers):

```php
use Bitrix\Main\Composite\StaticArea;

$area = new StaticArea('vendor_cart_counter');
$area->setStub('<span>0</span>');
$area->startDynamicArea();
echo (int)$basketCount;
$area->finishDynamicArea();
```

- Per-component switch without code: params `COMPOSITE_FRAME_MODE` (`A`/`Y`/`N`) and `COMPOSITE_FRAME_TYPE` (`AUTO`, `STATIC`, `DYNAMIC_WITH_STUB`, `DYNAMIC_WITHOUT_STUB`).
- Exclude a page: `\Bitrix\Main\Composite\Page::getInstance()->markNonCacheable()`.
- There is no `data-dynamic` attribute and no `composite_frame_mode` page property.
- Never leave personal data (name, cart, rights, CSRF tokens) in the static part. Test every dynamic area as a guest and as a user.

## Queries

- Select only needed fields; index columns used in `filter`/`order`.
- No N+1: `fetchCollection()` + `fill()`, or relations in `select` (`bitrix-orm`).
- Batch writes (`addMulti`, `updateMulti`) instead of per-row loops.
- ORM query cache for repeated reads; tagged data cache for assembled results.

## Read replicas

With the `cluster` module, `SELECT`s from `query()` may go to a slave. Pin read-after-write to the master:

```php
$pool = \Bitrix\Main\Application::getInstance()->getConnectionPool();
$pool->useMasterOnly(true);
try
{
    $row = OrderTable::getById($id)->fetch();
}
finally
{
    $pool->useMasterOnly(false);
}
```

Extra DBs (analytics, reports): a named connection in `connections` (`bitrix-database`).

## Checklist

- [ ] Every composite dynamic area checked as guest and as logged-in user.
- [ ] NGINX serves the composite cache in production.
- [ ] Slow queries found with SqlTracker and indexed.
- [ ] Cache and sessions on Redis/Memcached, not files, under load.
