# User fields (UF): schema, values, output

A UF belongs to an `ENTITY_ID` (`USER`, `BLOG_POST`, `HLBLOCK_7`, own `VENDOR_ITEM`); values live in `b_uts_<entity>` / `b_utm_<entity>`. `FIELD_NAME`: `UF_` + `[0-9A-Z_]`, 4–50 chars.

## Schema: `CUserTypeEntity` only

`\Bitrix\Main\UserFieldTable` is read-only (`getList`, `getMap`): `add/update/delete` throw `NotImplementedException`.

```php
$id = (new \CUserTypeEntity())->Add([
    'ENTITY_ID' => 'VENDOR_ITEM',
    'FIELD_NAME' => 'UF_COLOR',
    'USER_TYPE_ID' => 'enumeration',
    'MULTIPLE' => 'N',
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'I',            // N | I exact | E mask | S substring
    'EDIT_IN_LIST' => 'Y',
    'EDIT_FORM_LABEL' => ['ru' => 'Цвет', 'en' => 'Color'],
]);
if (!$id) {
    $error = $APPLICATION->GetException()?->GetString();
}

(new \CUserFieldEnum())->SetEnumValues($id, [
    'n0' => ['VALUE' => 'Red', 'XML_ID' => 'red', 'SORT' => 100],   // nN = new; existing: [<ID> => [...]], 'DEL' => 'Y' removes
]);
```

Create in module `DoInstall()` / a migration (`bitrix-sprint-migration`); check existence first with `CUserTypeEntity::GetList([], ['ENTITY_ID' => …, 'FIELD_NAME' => …])`. `(new \CUserTypeEntity())->Delete($id)` deletes the field with all its values.

Types: `string`, `string_formatted`, `integer`, `double`, `boolean`, `date`, `datetime`, `enumeration`, `file`, `url` (main); `iblock_element`, `iblock_section` (iblock); `hlblock` (highloadblock); `rich_text` (ui, BBCode, **Since ui 26.600**; fallback `string_formatted`). List at runtime: `$USER_FIELD_MANAGER->GetUserType()`.

## Values

- ORM tablet with `getUfId()` (`bitrix-orm`): select/filter `UF_*` directly; `add()/update()` with `UF_*` keys runs UF validation and saving. `UserTable::getUfId()` = `USER` → `UserTable::getList(['select' => ['ID', 'UF_COLOR']])`; `USER.UF_COLOR` only through a `USER` reference from another entity.
- `UserTable` is read-only: write users (and their UF) via `(new \CUser())->Update($id, ['UF_COLOR' => $enumId])`.
- Non-ORM entity: `$USER_FIELD_MANAGER->GetUserFields('VENDOR_ITEM', $itemId, LANGUAGE_ID)` → `['UF_COLOR' => [...definition, 'VALUE' => …]]`; validate with `CheckFields($entityId, $id, $fields)` (errors in `$APPLICATION->GetException()`), save with `Update($entityId, $id, $fields)`.

## Output

```php
global $USER_FIELD_MANAGER;
$fields = $USER_FIELD_MANAGER->GetUserFields('VENDOR_ITEM', $itemId, LANGUAGE_ID);

echo $USER_FIELD_MANAGER->GetPublicView($fields['UF_COLOR']);
echo $USER_FIELD_MANAGER->GetPublicEdit($fields['UF_COLOR'], ['NAME' => 'ITEM[UF_COLOR]']);
$text = $USER_FIELD_MANAGER->getPublicText($fields['UF_COLOR']);
```

- `GetPublicView/Edit` params: `NAME` (input name, default `FIELD_NAME`), `VALUE` (override), `bVarsFromForm` (take value from request).
- Resolution: events `main:onBeforeGetPublicView|Edit` (params by reference) → `main:onGetPublicView|Edit` (first `EventResult::SUCCESS` wins, its parameters = HTML) → `VIEW_CALLBACK|EDIT_CALLBACK` of the field, then of the type (every `BaseType` type → render component) → `VIEW_COMPONENT_NAME` → `bitrix:system.field.view|edit` → `main:onAfterGetPublicView|Edit` (HTML by reference, 3rd param). Override one field: put `VIEW_CALLBACK` = `fn(array $uf, array $params): string` into its array.
- `getPublicText()` renders the type's `main.public_text` template; for legacy (non-`BaseType`) types it returns raw `join(', ', VALUE)` — escape it yourself.
- `(new \Bitrix\Main\UserField\Renderer($uf, ['mode' => \Bitrix\Main\UserField\Types\BaseType::MODE_VIEW]))->render()` — `BaseType` types only (else `null`); pass `mode` in the ctor array (`MODE_VIEW` / `MODE_EDIT`), `setMode()` is ignored and a missing key raises a PHP warning. It does not load values: pass the array with `VALUE`.

## Admin pages (`CAdminList` / `CAdminForm`, compat)

- Form: `EditFormTab($entityId)` + `EditFormShowTab($entityId, $bVarsFromForm, $id)`; one field: `GetEditFormHTML($bVarsFromForm, $value, $uf)`. Save: `EditFormAddFields($entityId, $fields)` (from POST/FILES, `EDIT_IN_LIST = Y` only) → `Update()`.
- List: `AdminListAddHeaders($entityId, $headers)`, per row `AddUserFields($entityId, $row, $adminRow)`; filter: `AdminListAddFilterFields()`, `AdminListShowFilter()`, `AdminListAddFilter($entityId, $filter)`; `AdminListPrepareFields()` before saving inline edits. Own forms call `ShowScript()` once.
