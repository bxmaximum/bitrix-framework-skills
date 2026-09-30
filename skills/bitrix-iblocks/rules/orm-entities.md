# Compiled ORM classes

## Elements

Class `\Bitrix\Iblock\Elements\Element{ucfirst(API_CODE)}Table` (API_CODE `News` → `ElementNewsTable`). It autoloads on first reference (`Bitrix\Iblock\ORM\Loader`), so `ElementNewsTable::query()` works directly. Resolve dynamically:

```php
use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\IblockTable;

$elementClass = Iblock::wakeUp($iblockId)->getEntityDataClass(); // null + E_USER_WARNING without API_CODE
$entity = IblockTable::compileEntity('News');                     // ElementEntity|false (API_CODE or Iblock object)
$elementClass = $entity ? $entity->getDataClass() : null;
```

- Parent class: `ORM\ElementV1Table` or `ORM\ElementV2Table` by storage version (both extend `ORM\CommonElementTable`). `IblockTable::compileAllEntities()` compiles every iblock with an `API_CODE`.
- `SECTIONS`: ManyToMany to `SectionTable` via `SectionElementTable`, direct links only. `setIblockSectionId()` keeps it in sync with `IBLOCK_SECTION_ID`.
- `Bitrix\Iblock\ElementTable` knows only base fields and its `add/update/delete` **always return an error** (`ELEMENT_ENTITY_MESS_*_BLOCKED`). Use it for reads of base fields only.
- Don't override `getMap()` in a subclass of `Element{ApiCode}Table` (the compiled map wins). Use `runtime`/`registerRuntimeField()` per query; never reuse names of system fields or property codes.

## Sections

`Model\Section::compileEntityByIblock($iblock)` takes an ID, an `API_CODE` or an `Iblock` object and returns the FQCN of the runtime class `Section<IBLOCK_ID>Table` in namespace `Bitrix\Iblock` (or `null`). It adds `PARENT_SECTION`, UF entity `IBLOCK_{ID}_SECTION` (`UF_*` fields) and a default scope on `IBLOCK_ID`.

## Property values

Each property with a `CODE` becomes a field named after the `CODE` (properties without `CODE` are skipped). Single → reference, multiple → collection of value objects. Value entity `\Bitrix\Iblock\Elements\IblockProperty{PROPERTY_ID}Table`: `VALUE` (+ `DESCRIPTION` when enabled).

| Type | `VALUE` | Auto relation |
| --- | --- | --- |
| `S` | string | — |
| `N` | float | — |
| `F` | int | `FILE` (`b_file`) |
| `L` | int | `ITEM` (`PropertyEnumerationTable`: `VALUE`, `XML_ID`) |
| `E` | int | `ELEMENT` (only if the linked iblock has `API_CODE`) |
| `G` | int | `SECTION` (`SectionTable`) |

Custom property types can add fields through the `GetORMFields` callback.

## Side effects of ORM writes

Compiled element classes **do**: required-property check (`onBeforeAdd`), iblock tag cache clear (`CIBlock::clearIblockTagCache`), facet index update/delete, property rows removal on delete, the v2 single-value row on add. Section classes **do**: tag cache clear and tree recount (`LEFT/RIGHT_MARGIN`, `DEPTH_LEVEL`, `GLOBAL_ACTIVE`). The docs say you must do these by hand; the kernel already does them.

ORM **does not**: search index, SEO (inherited property) recalculation, rights set/check, workflow/business processes, `PREVIEW/DETAIL_PICTURE` resize, auto `CODE`, disk quota, catalog availability and SKU price recalculation, iblock event log, section property binding, classic `OnBeforeIBlockElement*`/`OnAfterIBlockElement*` events. Need any of these → `CIBlockElement::Add/Update/Delete`, `CIBlockSection`.

Section `$object->delete()` removes only the section: children and elements stay orphaned. `CIBlockSection::Delete($id)` cascades and cleans search and cache.
