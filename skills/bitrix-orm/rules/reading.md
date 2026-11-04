# Чтение

| Задача | Использовать |
| --- | --- |
| Новый запрос | `XxxTable::query()` и цепочка методов |
| OR / вложенные условия / сравнение столбцов | `Query::filter()` → `ConditionTree` |
| Сущность со связями и последующим изменением | `fetchObject()` / `fetchCollection()` |
| Плоский список, агрегаты, критичный по скорости код | `fetch()` / `fetchAll()` |
| Существующий код с массивами | `getList()` (совместимость) |
| Фильтр ORM как SQL `WHERE` | `Query::buildFilterSql($entity, $filter)` |

- `query()` создаёт `static::getQueryClass()` (таблеты элементов инфоблоков возвращают свой `Query`); `new Query($entity)` теряет это. Переопределение `getList()` не влияет на `query()`: помещай логику таблета в `getQueryClass()` / `setDefaultScope()`.
- Объекты не подходят для результата `GROUP BY` / агрегатов.
- Вычисляемые поля задавай объектами `ExpressionField`, не массивами выражений.
- `disableDataDoubling()` нужен только при дублировании строк фильтром 1:N. `enablePrivateFields()` включай только при необходимости.
- Неизвестное поле в любой части запроса вызывает `ArgumentException`; `addOrder()` принимает только `ASC`/`DESC`.

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

## Вычисляемые поля, агрегаты, DISTINCT

```php
use Bitrix\Main\ORM\Fields\ExpressionField;

$query = PostTable::query()
    ->setSelect(['AUTHOR_ID', new ExpressionField('CNT', 'COUNT(%s)', 'ID')])
    ->where('ACTIVE', 'Y')
    ->setGroup(['AUTHOR_ID'])
    ->having('CNT', '>', 5);
$stats = $query->fetchAll();

$authors = PostTable::query()->setSelect(['AUTHOR_ID'])->setDistinct()->fetchAll();   // SELECT DISTINCT
$sql = $query->getQuery();                                                                // SQL без выполнения
```

`setDistinct(false)` сбрасывает признак. `hasDistinct()` также true, если выбранный `ExpressionField` содержит `DISTINCT`: тогда флаг запроса снимается против двойного DISTINCT.

## Постраничная навигация

```php
$nav = (new \Bitrix\Main\UI\PageNavigation('posts'))->allowAllRecords(false)->setPageSize(20);
$nav->setRecordCount(PostTable::getCount($filter));   // до initFromUri(): ограничивает номер страницы
$nav->initFromUri();

$items = PostTable::query()
    ->setSelect(['ID', 'TITLE'])
    ->setFilter($filter)
    ->setOffset($nav->getOffset())
    ->setLimit($nav->getLimit())
    ->fetchCollection();
```

Другой вариант: `->countTotal(true)->exec()`, затем `$result->getCount()` (гарантирован тот же фильтр; число строк приходит после `initFromUri()`, поэтому номер не ограничивается). Без `countTotal(true)` вызов `getCount()` выбрасывает исключение. Для больших таблиц выбирай `limit + 1` строк вместо подсчёта.

## Устаревший `getList`

```php
$rows = PostTable::getList([
    'select' => ['ID', 'TITLE'],
    'filter' => ['=ACTIVE' => 'Y'],
    'order' => ['ID' => 'DESC'],
    'limit' => 20,
    'cache' => ['ttl' => 3600, 'cache_joins' => true],
])->fetchAll();
```

Только для существующего кода с массивами. Аналог `cache` в цепочке методов — `setCacheTtl()` / `cacheJoins()`.
