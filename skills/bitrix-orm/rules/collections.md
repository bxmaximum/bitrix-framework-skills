# Коллекции

`fetchCollection()` возвращает `EO_Xxx_Collection` (для своего класса переопредели `getCollectionClass()` и наследуй сгенерированный класс). Используй вместо `fetchObject()` в цикле, создающего N+1 запросов.

## API

| Метод | Действие |
| --- | --- |
| `add($obj)`, `$c[] = $obj` | Добавление; ArrayAccess поддерживает только его: `$c[0]`, `isset`, `unset` вызывают `NotImplementedException` |
| `getByPrimary($pk)`, `hasByPrimary($pk)`, `has($obj)`, `getAll()` | Поиск |
| `remove($obj)`, `removeByPrimary($pk)` | Удаление **только из памяти**, без обращения к БД |
| `save($ignoreEvents = false)` | Новые объекты → один `addMulti`; изменённые → один `updateMulti`, если все получили одинаковые скалярные значения, иначе по объекту; результат `ORM\Data\Result` |
| `fill($fields = FieldTypeMask::ALL)` | Загрузка недостающих полей/связей одним запросом; имя, список полей либо маска `FieldTypeMask` |
| `getTitleList()`, `getAuthorCollection()`, `fillAuthor()` | Генерируются для поля / связи |
| `collectValues()`, `count()`, `isEmpty()`, `merge($other)` | Вспомогательные методы; `merge()` требует тот же класс |
| `find(fn ($o, $k))`, `filter(fn)`, `walk(fn)` | **Since main 26.250\***; раньше используй `foreach` |
| `XxxCollection::wakeUp($rows)` | Восстановление из доверенных строк (кеша) без проверки БД |

Метода `delete()` **нет**. Для удаления строк используй `foreach ($c as $o) { $o->delete(); }` или `deleteByFilter()` (`rules/writing.md`).

```php
$posts = PostTable::query()->setSelect(['ID', 'TITLE'])->where('ACTIVE', 'Y')->fetchCollection();
$posts->fill(['AUTHOR']);                                 // один дополнительный запрос для всех авторов
foreach ($posts as $post)
{
    $post->setActive(false);
}
$result = $posts->save();

$draft = $posts->filter(static fn ($p) => $p->getTitle() === '');   // 26.250*; CollectionFilterException при несохранённом изменении состава
```

`filter()` проверяет изменения состава (`add`/`remove`, внутренний `_objectsChanges`), а не любые изменения полей объектов. Сохрани изменения состава перед фильтрацией. Это не требование сохранять каждое изменение свойства объекта.

## Ключи при обходе

Не разбирай ключи `foreach ($collection as $key => $obj)`. **Since main 26.800** строковый первичный ключ кодируется JSON (`'ru'` → `"\"ru\""`), составной становится JSON-объектом (`{"ID":1,"LANG_ID":"ru"}`); целочисленные не меняются. Используй `$obj->getId()`, `$obj->primary`, `getByPrimary()`.

## Коллекции связей

```php
$post = PostTable::query()->setSelect(['ID', 'COMMENTS', 'TAGS'])->where('ID', $id)->fetchObject();
$post->removeFromComments($comment);   // 1:N: FK получает NULL либо строка удаляется при CascadePolicy::FOLLOW
$post->removeFromTags($tag);           // M:N: удаляется строка посредника
$post->addToTags($otherTag);
$post->save();                         // изменения выше попадают в БД только здесь
```

Немагические формы: `removeAll('COMMENTS')`, `addTo('TAGS', $tag)`. Поведение 1:N настраивается через `->configureCascadeDeletePolicy(CascadePolicy::FOLLOW)` поля `OneToMany` (`Fields\Relations\CascadePolicy`).
