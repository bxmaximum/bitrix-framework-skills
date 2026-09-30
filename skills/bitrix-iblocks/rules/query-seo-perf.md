# Selections, SEO, performance

## Selections and property filters

```php
$elements = $elementClass::query()
    ->setSelect(['ID', 'NAME', 'PREVIEW_TEXT', 'AUTHOR', 'SOURCE.ITEM'])
    ->where('ACTIVE', 'Y')
    ->where('IBLOCK_SECTION_ID', $sectionId)
    ->where('AUTHOR.VALUE', 'Jane Smith')          // property value
    ->whereIn('SOURCE.VALUE', [$enumId1, $enumId2])
    ->setOrder(['SORT' => 'ASC', 'ID' => 'DESC'])
    ->setLimit(10)
    ->fetchCollection();

foreach ($elements as $el) {
    $el->getName();
    $el->getAuthor()?->getValue();              // S: value object → VALUE
    $el->getSource()?->getItem()?->getXmlId();  // L: enum row via ITEM
}
```

- Multiple property → collection: `foreach ($el->getTags()->getAll() as $value) { $value->getValue(); }`. Filtering by it JOINs and may repeat elements; add `->setDistinct()` or collect IDs.
- Sections of an element: select `SECTIONS` (ManyToMany, direct links only).
- ORM `getList` ignores rights and active dates: filter `ACTIVE`, `ACTIVE_FROM`/`ACTIVE_TO` yourself, check rights per `rules/basics.md`.

## SEO values

Templates are set via `IPROPERTY_TEMPLATES` in `CIBlock`/`CIBlockSection`/`CIBlockElement` `Add/Update`. Computed values:

```php
$values = (new \Bitrix\Iblock\InheritedProperty\ElementValues($iblockId, $elementId))->getValues();
$title = $values['ELEMENT_META_TITLE'] ?? '';
```

Also `SectionValues($iblockId, $sectionId)`, `IblockValues($iblockId)`. Computed values are cached per entity; setting templates resets them. ORM writes never touch templates or cached values.

## Performance

- Select only needed fields and properties; avoid `['*']` on iblocks with many properties. Each property in `select`/`where` is a JOIN (v1: into the shared table).
- ORM cache: `->setCacheTtl(3600)` plus `->cacheJoins(true)` (property fields are JOINs). ORM element/section writes clear the iblock tag cache; component caches follow tagged cache (`bitrix-caching`).
- Avoid N+1: one query with properties in `select`, or collect IDs and load in one `whereIn('ID', $ids)`.
- `ElementTable` is fine for ID/NAME lists; properties need the compiled class.
- Bulk imports via `CIBlockElement`: `Add($fields, false, false)` / `Update($id, $fields, false, false)` skip per-element search reindex (`$bUpdateSearch = false`); reindex once after the run.
