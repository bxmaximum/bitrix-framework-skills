# IDs, API boundary, iblock setup, rights

Iblock type (`b_iblock_type`) → iblock (`b_iblock`, bound to sites) → sections (tree, `b_iblock_section`) → elements (`b_iblock_element`) with properties (`b_iblock_property`).

## Identifiers

- `API_CODE`: `^[a-z][a-z0-9]{0,49}$` (case-insensitive; no `_`), unique across iblocks. Without it there is no property-aware ORM class and no REST (`REST_ON`). The docs list it as string(255); the kernel limits it to 50.
- `CODE`: symbolic code for URLs; `XML_ID`: external ID for exchange and migrations.

## ORM vs classic API

| Task | API |
| --- | --- |
| Iblock type | `CIBlockType::Add` (ORM does not write lang names) |
| Iblock | `CIBlock::Add/Update` (ORM does not bind sites, rights, SEO templates) |
| Property add/update/delete | `CIBlockProperty` (see storage versions) |
| Rights | `CIBlock::SetPermission`, `CIBlockRights`, `CIBlockSectionRights`, `CIBlockElementRights` |
| Element/section CRUD | compiled ORM (`rules/orm-entities.md`) or `CIBlockElement`/`CIBlockSection` |
| Thumbnails | `CFile::ResizeImageGet($fileId, ['width' => 300, 'height' => 300], BX_RESIZE_IMAGE_PROPORTIONAL)['src']` |
| Search index | `CIBlockElement::UpdateSearch($id, true)` |

## Create an iblock

```php
$iblock = new \CIBlock();
$iblockId = $iblock->Add([
    'IBLOCK_TYPE_ID' => 'news',
    'NAME'           => 'News',
    'CODE'           => 'news',
    'API_CODE'       => 'News',      // required for ORM
    'LID'            => ['s1'],
    'VERSION'        => 2,           // explicit; the default is 1
    'GROUP_ID'       => [2 => \CIBlockRights::PUBLIC_READ, 8 => \CIBlockRights::EDIT_ACCESS],
]);
if (!$iblockId) {
    throw new \RuntimeException($iblock->LAST_ERROR);
}
```

## Property storage versions

- **1 (default)**: all values in shared `b_iblock_element_property`. `CIBlock::Add` stores 2 only when 2 is passed; `IblockTable` defaults to `PROPERTY_STORAGE_COMMON`.
- **2**: `b_iblock_element_prop_s{ID}` (single values, one row per element) + `b_iblock_element_prop_m{ID}` (multiple). Faster selections with a moderate number of properties.
- `PropertyTable` is fine for **reading** property metadata. Write with `CIBlockProperty::Add/Update/Delete`: on v2 they also alter `prop_s`/`prop_m`. `PropertyTable::add/update/delete` touch only `b_iblock_property` and leave v2 storage inconsistent.

## Rights

- Simple mode: `GROUP_ID` on `Add`, or `CIBlock::SetPermission($iblockId, [$groupId => 'R'])`. Letters: `CIBlockRights::PUBLIC_READ` (R), `EDIT_ACCESS` (W), `FULL_ACCESS` (X). Extended mode (`RIGHTS_MODE = 'E'`): `CIBlockRights`/`CIBlockSectionRights`/`CIBlockElementRights`.
- Check: `CIBlockElementRights::UserHasRightTo($iblockId, $elementId, 'element_read')` (both modes, current `$USER` only; `false` without it), `CIBlock::GetPermission($iblockId, $userId)` (iblock-level letter). In agents/CLI decide the access model explicitly.
- `CIBlockElement::GetList` checks rights only with `'CHECK_PERMISSIONS' => 'Y'` (+ `MIN_PERMISSION`, default `R`; `PERMISSIONS_BY` = user ID). ORM `getList` never checks them.
- Never pass user-supplied filters straight into `GetList`: drop `CHECK_PERMISSIONS`, `PERMISSIONS_BY`, `MIN_PERMISSION`, `IBLOCK_ID` from them and set these server-side.
- Moving an element/section: check that the target section belongs to the same iblock and that the user has add rights there. Send `IBLOCK_SECTION_ID` only when moving (`0` = root).
- Lists module (Bitrix24 "Lists"): `CListPermissions::CheckAccess($USER, $typeId, $iblockId)` returns a letter (`CListPermissions::ACCESS_DENIED` … `IS_ADMIN`) or a negative int (`WRONG_IBLOCK_TYPE`, `WRONG_IBLOCK`, …). Treat `is_int($r) && $r < 0` as an error too, not only `ACCESS_DENIED`.
- `Bitrix\Iblock\Public\…` is mostly REST validation helpers, not a CRUD facade.
