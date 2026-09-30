# Properties, sections, elements

## Properties

```php
use Bitrix\Iblock\PropertyTable;

$property = new \CIBlockProperty();
$propId = $property->Add([
    'IBLOCK_ID'     => $iblockId,
    'NAME'          => 'Source',
    'CODE'          => 'SOURCE',                  // required: ORM skips properties without CODE
    'PROPERTY_TYPE' => PropertyTable::TYPE_LIST,  // S, N, F, L, E (element), G (section)
    'MULTIPLE'      => 'N',
]);
if (!$propId) { throw new \RuntimeException($property->LAST_ERROR); }

$enumId = (new \CIBlockPropertyEnum())->Add([
    'PROPERTY_ID' => $propId, 'VALUE' => 'Reuters', 'XML_ID' => 'reuters', 'SORT' => 10,
]);
```

User types (`PROPERTY_TYPE` + `USER_TYPE`, constants on `PropertyTable`):

- `S` + `USER_TYPE_HTML` — HTML editor; `S` + `USER_TYPE_DATE` / `USER_TYPE_DATETIME` — date.
- `E` + `USER_TYPE_SKU` — SKU link (`bitrix-catalog`).
- `S` + `USER_TYPE_DIRECTORY` (`directory`) — HL dictionary. `USER_TYPE_SETTINGS => ['TABLE_NAME' => $hl['TABLE_NAME']]` must equal the HL block's `TABLE_NAME` exactly (`b_hlbd_*` only for blocks created from the property UI). Stores `UF_XML_ID` (string); multiplicity via property `MULTIPLE`. The type class lives in module `highloadblock` → `bitrix-highloadblock` `rules/relations-directory.md`.
- Custom property types: keep `USER_TYPE_SETTINGS` an array of scalars. **Since iblock 26.0.100** `PropertyTable` reads `USER_TYPE_SETTINGS_LIST` with `allowed_classes => false`: stored objects come back as `__PHP_Incomplete_Class` (classic `CIBlockProperty*` already did this). Build DTOs after reading.

## Sections via ORM

```php
use Bitrix\Iblock\Model\Section;

$sectionClass = Section::compileEntityByIblock('News'); // '\Bitrix\Iblock\Section{ID}Table'

$parent = $sectionClass::createObject()
    ->setIblockId($iblockId)
    ->setName('Events')
    ->setCode('events')
    ->set('UF_MANAGER', 'John Doe')   // section UF via set('UF_*', …)
    ->save();
if (!$parent->isSuccess()) { /* $parent->getErrorMessages() */ }

$sectionClass::createObject()
    ->setIblockId($iblockId)
    ->setName('Exhibitions')
    ->setCode('exhibitions')
    ->setIblockSectionId($parent->getId())
    ->save();

$section = $sectionClass::query()
    ->setSelect(['ID', 'NAME', 'PARENT_SECTION', 'UF_*'])
    ->where('CODE', 'exhibitions')
    ->fetchObject();
$section?->getParentSection()?->getName();
```

Delete with `CIBlockSection::Delete($id)` (cascades). ORM `delete()` orphans children — see `rules/orm-entities.md`.

## Elements via ORM

```php
use Bitrix\Iblock\Elements\ElementNewsTable;
use Bitrix\Iblock\ORM\PropertyValue;

$element = ElementNewsTable::createObject()
    ->setName('Security Update')
    ->setCode('security-update')
    ->setActive(true)
    ->setIblockSectionId($sectionId)                        // also syncs SECTIONS
    ->set('AUTHOR', 'Jane Smith')                           // S
    ->set('SOURCE', $enumId)                                // L: enum value ID
    ->set('RELATED', $otherElementId)                       // E: element ID
    ->set('PHOTO', new PropertyValue($fileId, 'Main photo')) // F: file ID (+ description)
    ->addTo('TAGS', 'security')                             // multiple
    ->addTo('TAGS', '2026');

$result = $element->save();
if (!$result->isSuccess()) { /* $result->getErrorMessages() */ }

$element->removeFrom('TAGS', 'security'); // by value
$element->removeAll('TAGS');
```

- Scalars work for any property; `PropertyValue($value, $description)` only when a description is needed.
- Files: save first, `$fileId = \CFile::SaveFile(\CFile::MakeFileArray($path), 'iblock')`; set the ID.
- `addTo()` skips duplicates for `L/E/G/F`, allows them for `S/N`.

## Symbolic code (`CODE`)

ORM does not generate `CODE`. `(new \CIBlockElement())->generateMnemonicCode($name, $iblockId)` (same on `CIBlockSection`; `null` when transliteration is off) uses the iblock `CODE` field settings and `CUtil::translit`. **Since main 26.800** a name ending in punctuation keeps a trailing separator (`Скидка 10%` → `skidka_10_`). Existing codes don't change, but new ones differ. If codes must stay stable: `rtrim($code, '_')` (your `TRANS_OTHER`), then check uniqueness.
