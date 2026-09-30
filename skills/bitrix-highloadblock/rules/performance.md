# Performance and diagnostics

## Queries

- Select only needed fields. `=UF_CODE` can use an index; `%UF_NAME` and functions over columns in `runtime` cannot.
- Kill queries in loops: collect IDs, one query with `'@ID' => $ids`, map by `ID`. `_REF` JOIN vs "collect IDs + one query": measure.
- `count_total` only when the UI shows a total.
- Batches: `'>ID' => $lastId`, `order ID ASC`, `limit` (start at 500, tune by time/memory), fixed `'<=ID' => $maxId` for a stable run, persist `$lastId` to resume. No growing `offset`; at most one batch per transaction.

```php
$lastId = 0;
do {
    $rows = $dataClass::getList([
        'select' => ['ID', 'UF_CODE'],
        'filter' => ['>ID' => $lastId, '<=ID' => $maxId],
        'order' => ['ID' => 'ASC'], 'limit' => 500,
    ])->fetchAll();
    foreach ($rows as $row) { /* process */ $lastId = (int)$row['ID']; }
} while ($rows !== []);
```

## Cache

- Query cache is off by default: `'cache' => ['ttl' => 300]`; JOINs (`_REF`, `_SINGLE`) also need `'cache_joins' => true`.
- Managed-cache dir `orm_<TABLE_NAME>`; block descriptors `orm_b_hlblock_entity`. Data-class `add/update/delete` clear it. External writes: `$dataClass::getEntity()->cleanCache()`. `cleanCache()` is a no-op when `.settings.php` `cache_flags` has `<table>_max_ttl` = 0.
- Stale output? Identify the layer first: ORM cache, component cache or project cache (`bitrix-caching`).

## Indexes

Add them in migrations, never per request:

```php
$conn = \Bitrix\Main\Application::getConnection();
$table = $dataClass::getTableName();
if (!$conn->isIndexExists($table, ['UF_CATEGORY'])) {
    $conn->createIndex($table, 'ix_product_color_category', ['UF_CATEGORY']); // int/boolean/hlblock UF
}
```

`string` UF columns are `TEXT`: on MySQL `createIndex()` fails (error 1170, prefix length required) and it has no prefix option. Use raw DDL in the migration, e.g. `CREATE INDEX ix_product_color_code ON product_color (UF_CODE(64))`, or index a non-text column. PostgreSQL indexes `text` directly. Unique constraints have the same limit.

## Measure

```php
$conn = \Bitrix\Main\Application::getConnection();
$tracker = $conn->startTracker(true); // true = reset counters
// ... code under test ...
$conn->stopTracker();
foreach ($tracker->getQueries() as $query) { $query->getSql(); $query->getTime(); }
$tracker->getCounter(); $tracker->getTime();
```

The tracker keeps SQL with parameters: diagnostics only, never in production logs.

## Diagnostics

- Block not found: `getById()->fetch() === false` → stop before `compileEntity()` (it throws).
- Value not saved: unknown key dropped → `$dataClass::getEntity()->hasField()`.
- Missing table/column SQL error: structure drift or an unfinished migration. Fix the migration, no ad-hoc DDL.
- Duplicate rows: multiple fields, `_SINGLE` JOINs or `runtime` references. Fix the query, don't `array_unique`.
