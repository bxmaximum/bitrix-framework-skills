# Collections

`fetchCollection()` returns `EO_Xxx_Collection` (custom class: override `getCollectionClass()` and extend the generated one). Use it instead of `fetchObject()` in a loop (N+1).

## API

| Method | Does |
| --- | --- |
| `add($obj)`, `$c[] = $obj` | append (ArrayAccess is append-only: `$c[0]`, `isset`, `unset` throw `NotImplementedException`) |
| `getByPrimary($pk)`, `hasByPrimary($pk)`, `has($obj)`, `getAll()` | lookup |
| `remove($obj)`, `removeByPrimary($pk)` | detach **in memory only**, no DB call |
| `save($ignoreEvents = false)` | new objects → one `addMulti`; changed → one `updateMulti` if all changed the same scalar values, else per object; returns `ORM\Data\Result` |
| `fill($fields = FieldTypeMask::ALL)` | load missing fields/relations in one query; a field name, list or `FieldTypeMask` mask |
| `getTitleList()`, `getAuthorCollection()`, `fillAuthor()` | generated per field / relation |
| `collectValues()`, `count()`, `isEmpty()`, `merge($other)` | utilities (`merge()` needs the same class) |
| `find(fn ($o, $k))`, `filter(fn)`, `walk(fn)` | **Since main 26.250\***; fallback: `foreach` |
| `XxxCollection::wakeUp($rows)` | build from trusted rows (cache) without a DB check |

There is **no** `delete()`. Delete rows with `foreach ($c as $o) { $o->delete(); }` or `deleteByFilter()` (`rules/writing.md`).

```php
$posts = PostTable::query()->setSelect(['ID', 'TITLE'])->where('ACTIVE', 'Y')->fetchCollection();
$posts->fill(['AUTHOR']);                                 // one extra query for all authors
foreach ($posts as $post)
{
    $post->setActive(false);
}
$result = $posts->save();

$draft = $posts->filter(static fn ($p) => $p->getTitle() === '');   // 26.250*; throws CollectionFilterException on unsaved changes
```

## Iteration keys

Never parse `foreach ($collection as $key => $obj)` keys. **Since main 26.800** a string primary key is JSON-encoded (`'ru'` → `"\"ru\""`) and a composite one becomes a JSON object (`{"ID":1,"LANG_ID":"ru"}`); integer keys are unchanged. Use `$obj->getId()`, `$obj->primary`, `getByPrimary()`.

## Relation collections

```php
$post = PostTable::query()->setSelect(['ID', 'COMMENTS', 'TAGS'])->where('ID', $id)->fetchObject();
$post->removeFromComments($comment);   // 1:N: FK set to NULL, or row deleted with CascadePolicy::FOLLOW
$post->removeFromTags($tag);           // M:N: mediator row deleted
$post->addToTags($otherTag);
$post->save();                         // the changes above hit the DB only here
```

`removeAll('COMMENTS')`, `addTo('TAGS', $tag)` are the non-magic forms. Configure 1:N behaviour with `->configureCascadeDeletePolicy(CascadePolicy::FOLLOW)` on the `OneToMany` field (`Fields\Relations\CascadePolicy`).
