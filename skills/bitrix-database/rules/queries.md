# Raw queries, SqlHelper, placeholders

## Read

```php
use Bitrix\Main\DB\SqlExpression;

$rs = $db->query(new SqlExpression('SELECT ID, NAME FROM b_user WHERE ACTIVE = ?', 'Y'), 0, 100); // offset, limit
foreach ($rs as $row) { /* ... */ }                 // or while ($row = $rs->fetch())
$count = (int)$db->queryScalar('SELECT COUNT(*) FROM b_user');
```

- `fetch()` applies converters (DB dates → `Type\DateTime`); `fetchRaw()` returns driver values; `setConverters()` / `addFetchDataModifier()` customize rows.
- `Result` is forward-only: `fetchAll()` if you need a second pass.

## Write

```php
$id = $db->add('vendor_log', ['CODE' => $code, 'CREATED_AT' => new \Bitrix\Main\Type\DateTime()]);
$db->addMulti('vendor_log', [['CODE' => 'a'], ['CODE' => 'b']]);
$db->queryExecute(new SqlExpression('UPDATE ?# SET CNT = CNT + ?i WHERE ID = ?i', 'vendor_log', 1, $id));
$affected = $db->getAffectedRowsCount();
```

- Values are converted by column type and escaped. Unknown columns are dropped with `E_USER_WARNING`; it becomes an exception only if `exception_handling.exception_errors_types` includes `E_USER_WARNING`.
- `add()` returns the new ID. `addMulti()` returns `getInsertedId()`: the **first** row ID on MySQL, the **last** on PostgreSQL. Don't derive other IDs from it.
- There is no `insertBatch()`. For many rows use `addMulti()` in chunks or ORM `addMulti()` (`bitrix-orm`).

## SqlHelper

```php
$h = $db->getSqlHelper();
$h->quote('t.ID');                          // `t`.`ID` (PostgreSQL: "t"."ID")
$h->forSql($s);                             // escaped, without quotes
$h->convertToDbString('longer', 4);         // 'long'
$h->convertToDbInteger(1e10, 4);            // 2147483647 (4-byte clamp)
$h->convertToDbDateTime(new \Bitrix\Main\Type\DateTime()); // server-TZ literal
$h->getCurrentDateTimeFunction();           // NOW()
$h->addDaysToDateTime(30);                  // DATE_ADD(NOW(), INTERVAL 30 DAY) on MySQL
$h->getIsNullFunction($h->quote('CNT'), 0); // IFNULL(`CNT`, 0)
$h->getConcatFunction($h->quote('NAME'), "' '", $h->quote('LAST_NAME'));
```

Function arguments are not escaped: pass them through `quote()` / `convertToDb*()`.

## SqlExpression placeholders

| Placeholder | Result |
| --- | --- |
| `?` | quoted string; `Date` → date literal (`'2025-01-01'`), `DateTime` → `'Y-m-d H:i:s'` in server TZ |
| `?s` | quoted string. A `DateTime` turns into its culture-format string in user TZ: never use it for dates |
| `?i`, `?f` | int / float cast |
| `?#` | identifier via `quote()` |
| `?v` | upsert "new value" of the named column: `VALUES(col)` on MySQL, `EXCLUDED.col` on PostgreSQL |
| `?@` | **Since main 26.600\***: comma list of quoted values for `IN (?@)`; empty list throws `ArgumentException`. Fallback: `implode(',', array_map([$h, 'convertToDb'], $ids))` |

`null` gives `NULL` (except `?#`, `?@`). `\?` is a literal `?`. `(string)$expr` compiles the SQL.

## Upsert

```php
[$sql] = $h->prepareMerge(
    'vendor_counter',
    ['USER_ID', 'CODE'],                                          // unique key
    ['USER_ID' => $userId, 'CODE' => 'visits', 'CNT' => 1],       // insert
    ['CNT' => new SqlExpression('?# + 1', 'CNT')],                // update
);
$db->queryExecute($sql);

$db->queryExecute($h->prepareMergeValues('vendor_counter', ['USER_ID', 'CODE'], $rows, ['CNT'])); // many rows, CNT = new value
```

## Transactions

```php
$db->startTransaction();
try
{
    // queries or ORM writes on the same connection
    $db->commitTransaction();
}
catch (\Throwable $e)
{
    $db->rollbackTransaction();
    throw $e;
}
```

Nested calls use savepoints. On MySQL a nested `rollbackTransaction()` rolls back to the savepoint and then throws `TransactionException`; let it reach the outer level.
