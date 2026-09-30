# Reading

| Need | Use |
| --- | --- |
| New query | `XxxTable::query()` + fluent API |
| OR / nested / column-to-column conditions | `Query::filter()` → `ConditionTree` |
| Entity with relations, later mutation | `fetchObject()` / `fetchCollection()` |
| Flat list, aggregates, hot path | `fetch()` / `fetchAll()` |
| Existing array-style code | `getList()` (compat) |
| ORM filter as SQL `WHERE` | `Query::buildFilterSql($entity, $filter)` |

- `query()` creates `static::getQueryClass()` (iblock element tablets return their own `Query`); `new Query($entity)` loses that. A `getList()` override is ignored by `query()`: put table-specific logic in `getQueryClass()` / `setDefaultScope()`.
- Objects are not for `GROUP BY` / aggregate shapes.
- Runtime fields as `ExpressionField` objects, not array expressions.
- `disableDataDoubling()` only when a 1:N filter duplicates rows. `enablePrivateFields()` only when needed.
- Unknown field in any clause → `ArgumentException`; `addOrder()` accepts only `ASC`/`DESC`.

## `query()` + `ConditionTree`

```php
use Bitrix\Main\ORM\Query\Query;

$visibility = Query::filter()
    ->logic('or')
    ->where('AUTHOR_ID', $userId)
    ->where('PUBLIC', 'Y');

$rows = PostTable::query()
    ->setSelect(['ID', 'TITLE', 'AUTHOR_NAME' => 'AUTHOR.NAME'])
    ->where('ACTIVE', 'Y')
    ->where($visibility)
    ->setOrder(['CREATED_AT' => 'DESC'])
    ->setLimit(20)
    ->fetchAll();

$post = PostTable::query()->setSelect(['*', 'AUTHOR', 'COMMENTS'])->where('ID', $id)->fetchObject();
$authorName = $post?->getAuthor()?->getName();
```

## Runtime fields, aggregates, DISTINCT

```php
use Bitrix\Main\ORM\Fields\ExpressionField;

$query = PostTable::query()
    ->setSelect(['AUTHOR_ID', new ExpressionField('CNT', 'COUNT(%s)', 'ID')])
    ->where('ACTIVE', 'Y')
    ->setGroup(['AUTHOR_ID'])
    ->having('CNT', '>', 5);
$stats = $query->fetchAll();

$authors = PostTable::query()->setSelect(['AUTHOR_ID'])->setDistinct()->fetchAll();   // SELECT DISTINCT
$sql = $query->getQuery();                                                                // SQL, not executed
```

`setDistinct(false)` clears it. `hasDistinct()` is also true when a select `ExpressionField` contains `DISTINCT` (then the query flag is dropped to avoid double DISTINCT).

## Pagination

```php
$nav = (new \Bitrix\Main\UI\PageNavigation('posts'))->allowAllRecords(false)->setPageSize(20);
$nav->setRecordCount(PostTable::getCount($filter));   // before initFromUri(): clamps the page number
$nav->initFromUri();

$items = PostTable::query()
    ->setSelect(['ID', 'TITLE'])
    ->setFilter($filter)
    ->setOffset($nav->getOffset())
    ->setLimit($nav->getLimit())
    ->fetchCollection();
```

Alternative: `->countTotal(true)->exec()` then `$result->getCount()` (same filter guaranteed; count arrives after `initFromUri()`, so no clamping). `getCount()` without `countTotal(true)` throws. Huge tables: fetch `limit + 1` rows instead of counting.

## Legacy `getList`

```php
$rows = PostTable::getList([
    'select' => ['ID', 'TITLE'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['ID' => 'DESC'],
    'limit' => 20,
    'cache' => ['ttl' => 3600, 'cache_joins' => true],
])->fetchAll();
```

Only in existing array-style code. The fluent equivalent of `cache` is `setCacheTtl()` / `cacheJoins()`.
