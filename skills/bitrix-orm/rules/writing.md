# Writing

| Need | Use |
| --- | --- |
| One row from an array | `add()` / `update()` / `delete()` |
| Object state, relations | `EntityObject::save()`, collection `save()` |
| Many homogeneous rows | `addMulti($rows, $ignoreEvents = false)` / `updateMulti($primaries, $data, $ignoreEvents = false)` |
| Counter without read-modify-write | `update()` with `SqlExpression` |
| Mass delete, no per-row lifecycle | `DeleteByFilterTrait::deleteByFilter()` with a narrow filter |
| Upsert: update on conflict | `MergeTrait::merge()`, `AddMergeTrait::addMerge()` |
| Upsert: keep existing row | `AddInsertIgnoreTrait::addInsertIgnore()` |

## Arrays and objects

```php
$add = PostTable::add(['TITLE' => 'Hi', 'AUTHOR_ID' => $userId]);
if (!$add->isSuccess())
{
    return $add;                        // pass errors up (bitrix-result-and-errors)
}
$id = $add->getId();                    // array for a composite primary key

$upd = PostTable::update($id, ['TITLE' => 'Hello']);
$changed = $upd->getAffectedRowsCount();
PostTable::delete($id);                 // composite: delete(['POST_ID' => 1, 'TAG_ID' => 2])

$post = PostTable::createObject();
$post->setTitle('Title')->setAuthorId($userId);
$result = $post->save();

PostTable::updateMulti([100, 101], ['ACTIVE' => 'N']);
```

`ignoreEvents = true` on multi-writes is a deliberate trade-off, not a speed default.

## Atomic increment

```php
use Bitrix\Main\DB\SqlExpression;

PostTable::update($id, ['VIEWS' => new SqlExpression('?# + ?i', 'VIEWS', 1)]);
```

Placeholders: `bitrix-database`.

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

- An empty filter throws `ArgumentException` (truncate via the connection, deliberately).
- No events, no UF cleanup; `cleanCache()` is called.
- `ConditionTree` argument (`Query::filter()->where(...)`) **Since main 26.250\***; older kernels take arrays only.

## Merge / insert-ignore

```php
use Bitrix\Main\ORM\Data\Internal\MergeTrait;
use Bitrix\Main\ORM\Data\AddStrategy\Trait\AddInsertIgnoreTrait;

final class PortalLinkTable extends DataManager
{
    use MergeTrait;
}
PortalLinkTable::merge(
    ['PORTAL_ID' => $portalId, 'EXTERNAL_ID' => $externalId, 'TITLE' => $title],   // insert
    ['TITLE' => $title],                                                          // update on duplicate
);                                                                                // 3rd arg: unique columns, default PK

final class SyncMarkerTable extends DataManager
{
    use AddInsertIgnoreTrait;          // Since main 26.250*
}
$added = SyncMarkerTable::addInsertIgnore(['ENTITY_ID' => $entityId, 'MARKER' => $marker]);
```

- `merge()` (baseline) is raw SQL: no events, returns nothing, then `cleanCache()`.
- `AddStrategy\Trait\*` (**Since main 26.250\***): `addMerge()`/`addMergeMulti()`, `addInsertIgnore()`/`addInsertIgnoreMulti()` return `AddResult`; `MergeByDefaultTrait` / `InsertIgnoreByDefaultTrait` switch plain `add()`/`addMulti()` to that strategy. They run the normal add pipeline: `OnBeforeAdd`/`OnAdd`/`OnAfterAdd` fire even when the row was ignored or merged (the kernel docblocks saying "never fired" are stale); for an ignored row `getId()` is re-read by the unique fields. Fallback: `merge()` or `SqlHelper::prepareMerge()`.
- Ignore keeps the existing row, merge updates it: pick deliberately.
