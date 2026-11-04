# Выборки, SEO, производительность

## Выборки и фильтры свойств

```php
$elements = $elementClass::query()
    ->setSelect(['ID', 'NAME', 'PREVIEW_TEXT', 'AUTHOR', 'SOURCE.ITEM'])
    ->where('ACTIVE', 'Y')
    ->where('IBLOCK_SECTION_ID', $sectionId)
    ->where('AUTHOR.VALUE', 'Анна Иванова') // значение свойства
    ->whereIn('SOURCE.VALUE', [$enumId1, $enumId2])
    ->setOrder(['SORT' => 'ASC', 'ID' => 'DESC'])
    ->setLimit(10)
    ->fetchCollection();

foreach ($elements as $el) {
    $el->getName();
    $el->getAuthor()?->getValue(); // S: объект значения → VALUE
    $el->getSource()?->getItem()?->getXmlId(); // L: вариант списка через ITEM
}
```

- Множественное свойство — коллекция: `foreach ($el->getTags()->getAll() as $value) { $value->getValue(); }`. Фильтр добавляет JOIN, возможны повторы; используй `->setDistinct()` либо собери ID.
- Разделы элемента: `select SECTIONS`, ManyToMany только прямых связей.
- ORM `getList` игнорирует права и даты активности: фильтруй `ACTIVE`, `ACTIVE_FROM`/`ACTIVE_TO`, проверяй права по `rules/basics.md`.

## Значения SEO

Шаблоны задаются через `IPROPERTY_TEMPLATES` в `Add/Update` классов `CIBlock`/`CIBlockSection`/`CIBlockElement`. Вычисленные значения:

```php
$values = (new \Bitrix\Iblock\InheritedProperty\ElementValues($iblockId, $elementId))->getValues();
$title = $values['ELEMENT_META_TITLE'] ?? '';
```

Также `SectionValues($iblockId, $sectionId)`, `IblockValues($iblockId)`. Значения кешируются на сущность; установка шаблонов сбрасывает кеш. ORM-запись не меняет шаблоны и кеш значений.

## Производительность

- Выбирай нужные поля и свойства, избегай `['*']` при множестве свойств. Каждое свойство в `select`/`where` — JOIN, в v1 к общей таблице.
- Кеш ORM: `->setCacheTtl(3600)` и `->cacheJoins(true)` (свойства — JOIN). Запись элементов / разделов ORM чистит теги инфоблока; кеш компонентов использует тегированный кеш (`bitrix-caching`).
- Без N+1: один запрос со свойствами в `select` либо собрать ID и загрузить одним `whereIn('ID', $ids)`.
- `ElementTable` подходит для списков ID/NAME; свойствам нужен компилируемый класс.
- Массовый импорт `CIBlockElement`: `Add($fields, false, false)` / `Update($id, $fields, false, false)` отключают поэлементную индексацию (`$bUpdateSearch = false`). После запуска переиндексируй один раз.
