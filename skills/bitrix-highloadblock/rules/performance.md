# Производительность и диагностика

## Запросы

- Выбирай нужные поля. `=UF_CODE` может использовать индекс; `%UF_NAME` и функции столбца в `runtime` — нет.
- Убери запросы в циклах: собери ID, запрос `'@ID' => $ids`, индекс результата по `ID`. Выбор `_REF` JOIN либо отдельного пакетного запроса измеряй.
- `count_total` нужен, только если интерфейс показывает итоговое число.
- Пакеты: `'>ID' => $lastId`, `order ID ASC`, `limit` (начни с 500, подбери по времени / памяти), фиксированный `'<=ID' => $maxId`; сохраняй `$lastId` для продолжения. Без растущего `offset`; не больше пакета на транзакцию.

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

## Кеш

- Кеш запроса по умолчанию выключен: `'cache' => ['ttl' => 300]`; для JOIN (`_REF`, `_SINGLE`) нужен также `'cache_joins' => true`.
- Каталог управляемого кеша: `orm_<TABLE_NAME>`, описания блоков — `orm_b_hlblock_entity`. `add/update/delete` класса данных очищают его. Внешняя запись: `$dataClass::getEntity()->cleanCache()`. При `.settings.php` `cache_flags` `<table>_max_ttl = 0` метод `cleanCache()` ничего не делает.
- Устарел вывод: сначала найди слой — ORM, компонент либо проект (`bitrix-caching`).

## Индексы

Добавляй миграциями, не на каждом запросе:

```php
$conn = \Bitrix\Main\Application::getConnection();
$table = $dataClass::getTableName();
if (!$conn->isIndexExists($table, ['UF_CATEGORY'])) {
    $conn->createIndex($table, 'ix_product_color_category', ['UF_CATEGORY']); // UF integer/boolean/hlblock
}
```

UF `string` хранится как `TEXT`: MySQL `createIndex()` выдаст 1170 без длины префикса; такого параметра у метода нет. Используй DDL миграции: `CREATE INDEX ix_product_color_code ON product_color (UF_CODE(64))`, либо индексируй не текст. PostgreSQL индексирует `text` напрямую. Уникальные ограничения имеют тот же предел.

## Измерение

```php
$conn = \Bitrix\Main\Application::getConnection();
$tracker = $conn->startTracker(true); // true сбрасывает счётчики
// проверяемый код
$conn->stopTracker();
foreach ($tracker->getQueries() as $query) { $query->getSql(); $query->getTime(); }
$tracker->getCounter(); $tracker->getTime();
```

Трекер хранит SQL с параметрами: только диагностика, не журналы production.

## Диагностика

- Блок не найден: `getById()->fetch() === false`, остановись до `compileEntity()`, который бросит исключение.
- Значение не записано: неизвестный ключ отброшен; проверь `$dataClass::getEntity()->hasField()`.
- SQL-ошибка отсутствующей таблицы / колонки: расхождение структуры или незавершённая миграция. Исправь миграцию, без разового DDL.
- Дубли строк: множественные поля, `_SINGLE` JOIN либо связи `runtime`. Исправь запрос, не применяй `array_unique`.
