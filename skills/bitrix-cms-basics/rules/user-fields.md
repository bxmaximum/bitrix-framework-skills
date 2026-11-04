# Пользовательские поля (UF): схема, значения, вывод

UF принадлежит `ENTITY_ID` (`USER`, `BLOG_POST`, `HLBLOCK_7`, свой `VENDOR_ITEM`); значения — в `b_uts_<entity>` / `b_utm_<entity>`. `FIELD_NAME`: `UF_` + `[0-9A-Z_]`, 4–50 символов.

## Схема: только `CUserTypeEntity`

`\Bitrix\Main\UserFieldTable` доступен только для чтения (`getList`, `getMap`); `add/update/delete` бросают `NotImplementedException`.

```php
$id = (new \CUserTypeEntity())->Add([
    'ENTITY_ID' => 'VENDOR_ITEM',
    'FIELD_NAME' => 'UF_COLOR',
    'USER_TYPE_ID' => 'enumeration',
    'MULTIPLE' => 'N',
    'MANDATORY' => 'N',
    'SHOW_FILTER' => 'I',            // N | I точное | E маска | S подстрока
    'EDIT_IN_LIST' => 'Y',
    'EDIT_FORM_LABEL' => ['ru' => 'Цвет', 'en' => 'Color'],
]);
if (!$id) {
    $error = $APPLICATION->GetException()?->GetString();
}

(new \CUserFieldEnum())->SetEnumValues($id, [
    'n0' => ['VALUE' => 'Красный', 'XML_ID' => 'red', 'SORT' => 100], // nN — новое; существующее: [<ID> => [...]]; DEL = Y удаляет
]);
```

Создавай в `DoInstall()` модуля или миграции (`bitrix-sprint-migration`). Сначала проверь наличие через `CUserTypeEntity::GetList([], ['ENTITY_ID' => …, 'FIELD_NAME' => …])`. `(new \CUserTypeEntity())->Delete($id)` удаляет поле **со всеми значениями**.

Типы: `string`, `string_formatted`, `integer`, `double`, `boolean`, `date`, `datetime`, `enumeration`, `file`, `url` (main); `iblock_element`, `iblock_section` (iblock); `hlblock` (highloadblock); `rich_text` (ui, BBCode, **Since ui 26.600**; на старом ядре — `string_formatted`). Список при выполнении: `$USER_FIELD_MANAGER->GetUserType()`.

## Значения

- Таблет ORM с `getUfId()` (`bitrix-orm`): выбирай и фильтруй `UF_*` напрямую; `add()/update()` с ключами `UF_*` проверяет и сохраняет UF. `UserTable::getUfId() = USER`, поэтому `UserTable::getList(['select' => ['ID', 'UF_COLOR']])`. `USER.UF_COLOR` — только через связь `USER` из другой сущности.
- `UserTable` доступен только для чтения; пользователей и UF записывай через `(new \CUser())->Update($id, ['UF_COLOR' => $enumId])`.
- Вне ORM: `$USER_FIELD_MANAGER->GetUserFields('VENDOR_ITEM', $itemId, LANGUAGE_ID)` → `['UF_COLOR' => [...описание, 'VALUE' => …]]`; проверка `CheckFields($entityId, $id, $fields)` (ошибки в `$APPLICATION->GetException()`), сохранение `Update($entityId, $id, $fields)`.

## Вывод

```php
global $USER_FIELD_MANAGER;
$fields = $USER_FIELD_MANAGER->GetUserFields('VENDOR_ITEM', $itemId, LANGUAGE_ID);

echo $USER_FIELD_MANAGER->GetPublicView($fields['UF_COLOR']);
echo $USER_FIELD_MANAGER->GetPublicEdit($fields['UF_COLOR'], ['NAME' => 'ITEM[UF_COLOR]']);
$text = $USER_FIELD_MANAGER->getPublicText($fields['UF_COLOR']);
```

- Параметры `GetPublicView/Edit`: `NAME` (имя поля формы, по умолчанию `FIELD_NAME`), `VALUE` (подмена значения), `bVarsFromForm` (взять из запроса).
- Порядок: события `main:onBeforeGetPublicView|Edit` (параметры по ссылке) → `main:onGetPublicView|Edit` (первый `EventResult::SUCCESS`, его параметры — HTML) → `VIEW_CALLBACK|EDIT_CALLBACK` поля, затем типа (у `BaseType` — компонент вывода) → `VIEW_COMPONENT_NAME` → `bitrix:system.field.view|edit` → `main:onAfterGetPublicView|Edit` (HTML по ссылке, третий параметр). Для одного поля добавь в массив `VIEW_CALLBACK = fn(array $uf, array $params): string`.
- `getPublicText()` выводит шаблон `main.public_text` типа; для старых типов вне `BaseType` возвращает сырой `join(', ', VALUE)` — экранируй сам.
- `(new \Bitrix\Main\UserField\Renderer($uf, ['mode' => \Bitrix\Main\UserField\Types\BaseType::MODE_VIEW]))->render()` — только типы `BaseType`, иначе `null`. Передавай `mode` в массиве конструктора (`MODE_VIEW` / `MODE_EDIT`); `setMode()` игнорируется, отсутствие ключа вызывает PHP warning. Значения не загружаются: передай массив с `VALUE`.

## Административный интерфейс (`CAdminList` / `CAdminForm`, совместимость)

- Форма: `EditFormTab($entityId)` + `EditFormShowTab($entityId, $bVarsFromForm, $id)`; одно поле: `GetEditFormHTML($bVarsFromForm, $value, $uf)`. Сохранение: `EditFormAddFields($entityId, $fields)` (POST/FILES, только `EDIT_IN_LIST = Y`) → `Update()`.
- Список: `AdminListAddHeaders($entityId, $headers)`, на строку `AddUserFields($entityId, $row, $adminRow)`; фильтр: `AdminListAddFilterFields()`, `AdminListShowFilter()`, `AdminListAddFilter($entityId, $filter)`; перед сохранением правок строк — `AdminListPrepareFields()`. В своих формах один раз вызови `ShowScript()`.
