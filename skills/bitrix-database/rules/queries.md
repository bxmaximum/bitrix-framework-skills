# SQL-запросы, SqlHelper и плейсхолдеры

## Чтение

```php
use Bitrix\Main\DB\SqlExpression;

$rs = $db->query(new SqlExpression('SELECT ID, NAME FROM b_user WHERE ACTIVE = ?', 'Y'), 0, 100); // смещение, лимит
foreach ($rs as $row) { /* ... */ }                 // либо while ($row = $rs->fetch())
$count = (int)$db->queryScalar('SELECT COUNT(*) FROM b_user');
```

- `fetch()` применяет преобразователи (даты БД → `Type\DateTime`); `fetchRaw()` возвращает значения драйвера; `setConverters()` / `addFetchDataModifier()` меняют обработку строк.
- `Result` читается только вперёд; для повторного прохода используй `fetchAll()`.

## Запись

```php
$id = $db->add('vendor_log', ['CODE' => $code, 'CREATED_AT' => new \Bitrix\Main\Type\DateTime()]);
$db->addMulti('vendor_log', [['CODE' => 'a'], ['CODE' => 'b']]);
$db->queryExecute(new SqlExpression('UPDATE ?# SET CNT = CNT + ?i WHERE ID = ?i', 'vendor_log', 1, $id));
$affected = $db->getAffectedRowsCount();
```

- Значения приводятся к типу столбца и экранируются. Неизвестные столбцы отбрасываются с `E_USER_WARNING`; исключением это станет только при включении `E_USER_WARNING` в `exception_handling.exception_errors_types`.
- `add()` возвращает новый ID. `addMulti()` возвращает `getInsertedId()`: ID **первой** строки в MySQL и **последней** в PostgreSQL. Не вычисляй остальные ID из него.
- Метода `insertBatch()` нет. Большие наборы пиши порциями через `addMulti()` или ORM `addMulti()` (`bitrix-orm`).

## SqlHelper

```php
$h = $db->getSqlHelper();
$h->quote('t.ID');                          // `t`.`ID` (PostgreSQL: "t"."ID")
$h->forSql($s);                             // экранирование без кавычек
$h->convertToDbString('longer', 4);         // 'long'
$h->convertToDbInteger(1e10, 4);            // 2147483647 (ограничение 4-байтового числа)
$h->convertToDbDateTime(new \Bitrix\Main\Type\DateTime()); // литерал в часовом поясе сервера
$h->getCurrentDateTimeFunction();           // NOW()
$h->addDaysToDateTime(30);                  // DATE_ADD(NOW(), INTERVAL 30 DAY) в MySQL
$h->getIsNullFunction($h->quote('CNT'), 0); // IFNULL(`CNT`, 0)
$h->getConcatFunction($h->quote('NAME'), "' '", $h->quote('LAST_NAME'));
```

Аргументы функций не экранируются: пропускай их через `quote()` / `convertToDb*()`.

## SqlExpression placeholders

| Плейсхолдер | Результат |
| --- | --- |
| `?` | Строка в кавычках; `Date` → литерал даты (`'2025-01-01'`), `DateTime` → `'Y-m-d H:i:s'` в часовом поясе сервера |
| `?s` | Строка в кавычках. `DateTime` превращается в строку культурного формата в часовом поясе пользователя: не используй для дат |
| `?i`, `?f` | Приведение к int / float |
| `?#` | Идентификатор через `quote()` |
| `?v` | Новое значение столбца в upsert: `VALUES(col)` в MySQL, `EXCLUDED.col` в PostgreSQL |
| `?@` | **Since main 26.600\***: список значений в кавычках для `IN (?@)`; пустой список вызывает `ArgumentException`. Раньше: `implode(',', array_map([$h, 'convertToDb'], $ids))` |

`null` даёт `NULL` (кроме `?#`, `?@`). `\?` означает буквальный `?`. `(string)$expr` компилирует SQL.

## Upsert

```php
[$sql] = $h->prepareMerge(
    'vendor_counter',
    ['USER_ID', 'CODE'],                                          // уникальный ключ
    ['USER_ID' => $userId, 'CODE' => 'visits', 'CNT' => 1],       // вставка
    ['CNT' => new SqlExpression('?# + 1', 'CNT')],                // обновление
);
$db->queryExecute($sql);

$db->queryExecute($h->prepareMergeValues('vendor_counter', ['USER_ID', 'CODE'], $rows, ['CNT'])); // много строк, CNT = новое значение
```

## Транзакции

```php
$db->startTransaction();
try
{
    // запросы или записи ORM на том же соединении
    $db->commitTransaction();
}
catch (\Throwable $e)
{
    $db->rollbackTransaction();
    throw $e;
}
```

Вложенные вызовы используют точки сохранения. В MySQL вложенный `rollbackTransaction()` откатывается до точки сохранения, затем выбрасывает `TransactionException`; передавай его до внешнего уровня.
