# Page navigation

`\Bitrix\Main\UI\PageNavigation` only computes numbers from the URL: no DB access, no markup. You count rows, apply offset/limit, render `bitrix:main.pagenavigation`.

## Flow

```php
use Bitrix\Main\UI\PageNavigation;

$filter = ['=ACTIVE' => 'Y'];

$nav = new PageNavigation('nav-items');      // URL param / SEF segment, unique per page
$nav->allowAllRecords(false)
    ->setPageSizes([20, 50, 100])             // whitelist for ?nav-items=page-2-size-50
    ->setPageSize(20)
    ->setRecordCount(ItemTable::getCount($filter)); // same filter as the data query
$nav->initFromUri();                          // returns void

if ($nav->getPageCount() === 0) {
    return [];                                // 0 rows: page clamps to 0, offset would be -20
}

$rows = ItemTable::getList([
    'filter' => $filter,
    'order' => ['ID' => 'DESC'],
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
])->fetchAll();
```

- Order matters: `allowAllRecords()` → `setPageSizes()` → `setPageSize()` → `setRecordCount()` → `initFromUri()`. `initFromUri()` accepts the URL `size` only if it is in `setPageSizes()` (empty list = ignored), `page-all` only if allowed, and clamps the page to the page count only when the count is already set.
- Defaults: size 20, no sizes, `page-all` off, current page 1.
- URLs: GET `?nav-items=page-3-size-20` / `page-all`; SEF `…/nav-items/page-3/size-20/` (read only when the GET param is absent; the path must still route to your page).
- `getOffset()` = (page − 1) × size; `getLimit()` = size. In all-mode offset 0, limit = record count — `null` if the count was never set (unlimited query).
- `setCurrentPage()` and `setPageSize()` are not validated.
- `allowAllRecords(true)` lets any visitor load the whole table with `page-all` — never on large tables. `allRecordsAllowed()` = permitted, `allRecordsShown()` = active now.
- Getters for custom UI: `getRecordCount()`, `getPageCount()`, `getCurrentPage()`, `getPageSize()`, `getPageSizes()`, `getId()`.
- `addParams(Uri $uri, bool $sef, $page, $size = null)` / `clearParams($uri, $sef)` mutate the passed `Uri` — `clone` it per link.

Count from the same query: `'count_total' => true` in `getList()` (or `->countTotal(true)`), then `$nav->setRecordCount($result->getCount())`. The count arrives after `initFromUri()`, so no clamp: check `getCurrentPage() > getPageCount()` yourself. `getCount()` without `count_total` throws.

Big tables, no COUNT: `setPageSize(N)`, `initFromUri()`, query `limit = getLimit() + 1`; if N + 1 rows came, a next page exists: `setRecordCount(getOffset() + rowCount)`, drop the extra row.

## Other classes

- `ReversePageNavigation($id, $count)` — count is a required ctor arg; default page = last (newest first, "1" shown on the highest page); page count = floor(count / size), the remainder joins the first shown page (limit up to 2×size−1). Page above the count gives a negative offset: set size before `initFromUri()`, never `setCurrentPage()` unchecked. Not for the no-COUNT pattern.
- `AdminPageNavigation($id)` — sizes 10…500, `page-all` allowed, calls `initFromUri()` in the ctor: only `setRecordCount()` afterwards. Admin lists only.
- Controllers: an autowired `PageNavigation` action param has id `nav`, sizes 1–50, no count — see `bitrix-controllers`.

## `bitrix:main.pagenavigation`

```php
$APPLICATION->IncludeComponent('bitrix:main.pagenavigation', '', [
    'NAV_OBJECT' => $nav,
    'SEF_MODE' => 'N',
    'PAGE_WINDOW' => 5,
]);
```

- `NAV_OBJECT` not a `PageNavigation` → renders nothing. Hidden when ≤ 1 page (and not all-mode) unless `SHOW_ALWAYS` = `'Y'`.
- `SEF_MODE` / `SHOW_ALWAYS` are on only for `'Y'` or `true`; `BASE_LINK` overrides the base URL (AJAX lists).
- Templates: `.default` (`TEMPLATE_THEME` = `blue|green|yellow|red`), `modern`, `grid`, `admin`. `admin` alone renders "N–M of TOTAL" (`SHOW_COUNT`) and the size selector, but its links call `<TABLE_ID>.GetAdminList()` — admin pages only.
- Reverse navigation is detected automatically (`REVERSED_PAGES`).
- Own markup: copy a template to `/local/templates/<site_template>/components/bitrix/main.pagenavigation/<name>/`. `$arResult`: `RECORD_COUNT`, `PAGE_COUNT`, `CURRENT_PAGE`, `PAGE_SIZE`, `PAGE_SIZES`, `ALL_RECORDS`, `SHOW_ALL`, `REVERSED_PAGES`, `START_PAGE`, `END_PAGE`, `FIRST_RECORD`, `LAST_RECORD`, `URL`; links: `$component->replaceUrlTemplate($page, $size)`.
- Inside a cached component, the page must be in the cache key — `bitrix-components`.
