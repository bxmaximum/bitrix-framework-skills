# Manage blocks, fields, storage

## Naming and limits

- `NAME`: `^[A-Z][A-Za-z0-9]*$`, ≤ 100 chars, must not end with `Table`. `Collection` (any case) is rejected **Since highloadblock 25.100\***; on older versions avoid it yourself.
- `TABLE_NAME`: `^[a-z0-9_]+$`, ≤ 64 chars. Both unique; the table must not exist yet.
- `HighloadBlockLangTable`: `LID` ≤ 2, `NAME` ≤ 100. `HighloadBlockRightsTable.ACCESS_CODE` ≤ 50.

## Create, look up, change

```php
use Bitrix\Highloadblock\HighloadBlockTable;

$hl = HighloadBlockTable::getList(['select' => ['ID'], 'filter' => ['=NAME' => 'ProductColor']])->fetch();
if (!$hl) {
    $result = HighloadBlockTable::add(['NAME' => 'ProductColor', 'TABLE_NAME' => 'product_color']);
    if (!$result->isSuccess()) {
        throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
    }
    $hlId = (int)$result->getId();
}
```

- `add()` creates the table with a BIGINT `ID`. If `CREATE TABLE` fails it deletes the description row and returns an error **Since highloadblock 25.100\***. Older versions throw and leave the row behind: delete it before retrying.
- Non-throwing lookup: `HighloadBlockTable::resolveHighloadblock($idOrName): ?array` (int or digit-leading string = ID, other string = `NAME`). ID/NAME lookups are ORM-cached for 86400 s **Since highloadblock 25.100\***. The cache goes stale only if `b_hlblock_entity` is edited bypassing ORM.
- Object API: `HighloadBlockTable::getById($id)->fetchObject()?->getEntityDataClass()`. A compiled class exposes its row: `$dataClass::getHighloadBlock()['ID']`.
- `getList` extras: `FIELDS_COUNT`, `'LANG_NAME' => 'LANG.NAME'`. Lang titles: `HighloadBlockLangTable::add(['ID' => $hlId, 'LID' => 'en', 'NAME' => '…'])`.
- `update()`: a new `NAME` renames the class and every event name (handlers orphaned). A new `TABLE_NAME` renames the table and its multiple-value tables; then update each directory property's `TABLE_NAME` (`rules/relations-directory.md`).
- `delete()` removes records, files, UFs, lang rows, rights and tables. Irreversible, back up first. UF registry rows go by raw SQL, so `OnBeforeUserTypeDelete`/`OnAfterUserTypeDelete` do not fire.
- `HighloadBlockTable::cleanCache()` also resets `CIBlockPropertyDirectory` static caches.

## User fields

Attach via `CUserTypeEntity`, `ENTITY_ID = HighloadBlockTable::compileEntityId($hlId)` (`HLBLOCK_7`).

- `FIELD_NAME`: `UF_` + uppercase Latin/digits/`_`, 4–50 chars. A `_REF` suffix is rejected for `HLBLOCK_*` entities (reserved for references). Read the error from `$APPLICATION->GetException()`.
- Types (`USER_TYPE_ID`): `string`, `integer`, `double`, `boolean`, `datetime`, `date`, `file`, `enumeration`, `hlblock`, … `rich_text` (BBCode, ui module) **Since ui 26.600**. Older kernels: `string` with your own BBCode handling.
- `Add()` defaults: `SORT` 100, `MULTIPLE`/`MANDATORY` `N`, `SHOW_IN_LIST` `Y` (columns in `highloadblock.list`), `EDIT_IN_LIST` `Y`, `IS_SEARCHABLE` `N`, `SHOW_FILTER` `N` (`N|I|E|S` = none/exact/mask/substring).

```php
$entityId = HighloadBlockTable::compileEntityId($hlId);
$existing = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_CODE'])->Fetch();
if (!$existing) {
    $id = (new \CUserTypeEntity())->Add([
        'ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_CODE', 'USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y',
    ]);
    // $id === false → $APPLICATION->GetException()?->GetString()
} elseif ($existing['USER_TYPE_ID'] !== 'string' || $existing['MULTIPLE'] !== 'N') {
    throw new \RuntimeException('UF_CODE exists with another type'); // never silently reuse
}
```

- `Update()` cannot change `ENTITY_ID`, `FIELD_NAME`, `USER_TYPE_ID`, `MULTIPLE`: add a new field, copy values, delete the old one. Label updates replace all stored labels.
- `Delete()` drops the column/table; file fields delete their files.

## Storage layout

- `TABLE_NAME`: `ID` + one column per single UF (`ALTER TABLE ADD` on field add). `string` UF → `TEXT` column (not varchar); `integer`/`boolean`/`hlblock` → int.
- Multiple UF → table `<TABLE_NAME>_<lowercase field>` (`HighloadBlockTable::getMultipleValueTableName()`) with `ID`, `VALUE` and indexes `IX_UTM_HL<hlId>_<ufId>_ID/_VALUE`. ORM class `<Name>Utm<CamelField>`. The main table also keeps a serialized copy. `DataManager` syncs both.

## Recompile

The first `compileEntity()` per request builds the class; later calls return it. After UF changes use a new request. In the same request: re-fetch the row, call `compileEntity($row, true)` (rebuilds the map but cannot redefine the PHP class), then check `$entity->hasField('UF_X')`. `NAME`/`TABLE_NAME` changes always need a new request.

## Migrations and transfer

- Use `sprint.migration` (`Hlblock` helper, skill `bitrix-sprint-migration`). Order: find/create block by `NAME` → each UF by `ENTITY_ID`+`FIELD_NAME` (add missing, fail on type mismatch) → copy values before replacing a field → records in a new request. Stop on the first error; never run the same migration concurrently.
- Post-check: `getById()` `NAME`/`TABLE_NAME`, `CUserTypeEntity::GetList(['ENTITY_ID' => …])` codes/types/`MULTIPLE`, `compileEntity()` fields, a test add/read.
- XML export/import is admin UI only (`/bitrix/admin/highloadblock_export.php`, `highloadblock_import.php`; no PHP API). On import, pick an external key field (`ID` or a string/int UF). Without one, every row is added again as a duplicate. Move the sibling files directory with the XML.
