# Запись

| Задача | Использовать |
| --- | --- |
| Одна строка из массива | `add()` / `update()` / `delete()` |
| Состояние объекта, связи | `EntityObject::save()`, `save()` коллекции |
| Много однородных строк | `addMulti($rows, $ignoreEvents = false)` / `updateMulti($primaries, $data, $ignoreEvents = false)` |
| Счётчик без чтения перед записью | `update()` с `SqlExpression` |
| Массовое удаление без обработки каждой строки | `DeleteByFilterTrait::deleteByFilter()` с узким фильтром |
| Upsert: обновление при конфликте | `MergeTrait::merge()`, `AddMergeTrait::addMerge()` |
| Upsert: сохранение существующей строки | `AddInsertIgnoreTrait::addInsertIgnore()` |

## Массивы и объекты

```php
$add = PostTable::add(['TITLE' => 'Hi', 'AUTHOR_ID' => $userId]);
if (!$add->isSuccess())
{
    return $add;                        // передать ошибки выше (bitrix-result-and-errors)
}
$id = $add->getId();                    // массив при составном первичном ключе

$upd = PostTable::update($id, ['TITLE' => 'Hello']);
$changed = $upd->getAffectedRowsCount();
PostTable::delete($id);                 // составной ключ: delete(['POST_ID' => 1, 'TAG_ID' => 2])

$post = PostTable::createObject();
$post->setTitle('Title')->setAuthorId($userId);
$result = $post->save();

PostTable::updateMulti([100, 101], ['ACTIVE' => 'N']);
```

`ignoreEvents = true` при пакетной записи — осознанный компромисс, а не стандартное ускорение.

## Атомарное увеличение

```php
use Bitrix\Main\DB\SqlExpression;

PostTable::update($id, ['VIEWS' => new SqlExpression('?# + ?i', 'VIEWS', 1)]);
```

Плейсхолдеры: `bitrix-database`.

## `deleteByFilter`

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Data\Internal\DeleteByFilterTrait;

final class ArchivedPostTable extends DataManager
{
    use DeleteByFilterTrait;
}

ArchivedPostTable::deleteByFilter(['=AUTHOR_ID' => $authorId, '=ACTIVE' => 'N']);
```

- Пустой фильтр вызывает `ArgumentException` (очистка таблицы через соединение — отдельное осознанное действие).
- Нет событий и очистки UF; вызывается `cleanCache()`.
- Аргумент `ConditionTree` (`Query::filter()->where(...)`) доступен **Since main 26.250\***; раньше принимался только массив.

## Merge / вставка с игнорированием конфликта

```php
use Bitrix\Main\ORM\Data\Internal\MergeTrait;
use Bitrix\Main\ORM\Data\AddStrategy\Trait\AddInsertIgnoreTrait;

final class PortalLinkTable extends DataManager
{
    use MergeTrait;
}
PortalLinkTable::merge(
    ['PORTAL_ID' => $portalId, 'EXTERNAL_ID' => $externalId, 'TITLE' => $title],   // вставка
    ['TITLE' => $title],                                                          // обновление при конфликте
);                                                                                // третий аргумент: уникальные столбцы, по умолчанию PK

final class SyncMarkerTable extends DataManager
{
    use AddInsertIgnoreTrait;          // Since main 26.250*
}
$added = SyncMarkerTable::addInsertIgnore(['ENTITY_ID' => $entityId, 'MARKER' => $marker]);
```

- `merge()` (baseline) выполняет прямой SQL без событий, ничего не возвращает, затем вызывает `cleanCache()`.
- `AddStrategy\Trait\*` (**Since main 26.250\***): `addMerge()`/`addMergeMulti()`, `addInsertIgnore()`/`addInsertIgnoreMulti()` возвращают `AddResult`; `MergeByDefaultTrait` / `InsertIgnoreByDefaultTrait` переключают обычные `add()`/`addMulti()` на эту стратегию. Проходит обычная цепочка добавления: `OnBeforeAdd`/`OnAdd`/`OnAfterAdd` срабатывают даже при игнорировании или слиянии строки (комментарии ядра "never fired" устарели); при игнорировании `getId()` повторно читается по уникальным полям. На старом ядре: `merge()` или `SqlHelper::prepareMerge()`.
- Игнорирование сохраняет существующую строку, слияние обновляет её. Выбирай осознанно.
