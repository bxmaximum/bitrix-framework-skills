# ID, границы API, настройка инфоблока, права

Тип (`b_iblock_type`) → инфоблок (`b_iblock`, привязка к сайтам) → разделы (дерево `b_iblock_section`) → элементы (`b_iblock_element`) со свойствами (`b_iblock_property`).

## Идентификаторы

- `API_CODE`: `^[a-z][a-z0-9]{0,49}$` без учёта регистра, без `_`, уникален между инфоблоками. Без него нет ORM со свойствами и REST (`REST_ON`). Документация указывает string(255), ядро ограничивает 50 символами.
- `CODE`: символьный код URL; `XML_ID`: внешний ID обмена и миграций.

## ORM и классический API

| Задача | API |
| --- | --- |
| Тип инфоблока | `CIBlockType::Add`: ORM не пишет названия языков |
| Инфоблок | `CIBlock::Add/Update`: ORM не привязывает сайты, права, SEO-шаблоны |
| Добавление / изменение / удаление свойства | `CIBlockProperty`, с учётом версии хранения |
| Права | `CIBlock::SetPermission`, `CIBlockRights`, `CIBlockSectionRights`, `CIBlockElementRights` |
| CRUD элемента / раздела | Компилируемый ORM. Условия для классического API см. в `rules/orm-entities.md` |
| Миниатюры | `CFile::ResizeImageGet($fileId, ['width' => 300, 'height' => 300], BX_RESIZE_IMAGE_PROPORTIONAL)['src']` |
| Индекс поиска | `CIBlockElement::UpdateSearch($id, true)` |

## Создание инфоблока

```php
$iblock = new \CIBlock();
$iblockId = $iblock->Add([
    'IBLOCK_TYPE_ID' => 'news',
    'NAME'           => 'Новости',
    'CODE'           => 'news',
    'API_CODE'       => 'News',      // обязателен для ORM
    'LID'            => ['s1'],
    'VERSION'        => 2,           // явно; по умолчанию 1
    'GROUP_ID'       => [2 => \CIBlockRights::PUBLIC_READ, 8 => \CIBlockRights::EDIT_ACCESS],
]);
if (!$iblockId) {
    throw new \RuntimeException($iblock->LAST_ERROR);
}
```

## Версии хранения свойств

- **1, по умолчанию:** значения в общей `b_iblock_element_property`. `CIBlock::Add` сохраняет 2 только при явной передаче; у `IblockTable` стандарт — `PROPERTY_STORAGE_COMMON`.
- **2:** `b_iblock_element_prop_s{ID}` (одиночные, строка на элемент) + `b_iblock_element_prop_m{ID}` (множественные). Быстрее при умеренном числе свойств.
- `PropertyTable` подходит для **чтения** метаданных. Запись: `CIBlockProperty::Add/Update/Delete`, для v2 меняют также `prop_s`/`prop_m`. `PropertyTable::add/update/delete` меняют лишь `b_iblock_property`, нарушая хранение v2.

## Права

- Простой режим: `GROUP_ID` при `Add` либо `CIBlock::SetPermission($iblockId, [$groupId => 'R'])`. `CIBlockRights::PUBLIC_READ` (R), `EDIT_ACCESS` (W), `FULL_ACCESS` (X). Расширенный (`RIGHTS_MODE = 'E'`): `CIBlockRights`/`CIBlockSectionRights`/`CIBlockElementRights`.
- Проверка: `CIBlockElementRights::UserHasRightTo($iblockId, $elementId, 'element_read')` (оба режима, только текущий `$USER`, без него `false`), `CIBlock::GetPermission($iblockId, $userId)` (буква уровня инфоблока). Для агентов / CLI явно определи модель доступа.
- `CIBlockElement::GetList` проверяет права только с `'CHECK_PERMISSIONS' => 'Y'`; `MIN_PERMISSION` по умолчанию R, `PERMISSIONS_BY` — ID пользователя. ORM `getList` прав не проверяет.
- Не передавай фильтр пользователя в `GetList` напрямую: убери `CHECK_PERMISSIONS`, `PERMISSIONS_BY`, `MIN_PERMISSION`, `IBLOCK_ID`, задай на сервере.
- Перенос элемента / раздела: целевой раздел должен быть в том же инфоблоке, пользователь иметь право добавления. `IBLOCK_SECTION_ID` отправляй только при переносе; 0 — корень.
- Модуль списков Битрикс24: `CListPermissions::CheckAccess($USER, $typeId, $iblockId)` возвращает букву (`CListPermissions::ACCESS_DENIED` … `IS_ADMIN`) либо отрицательный int (`WRONG_IBLOCK_TYPE`, `WRONG_IBLOCK`, …). Ошибка — также `is_int($r) && $r < 0`, не только `ACCESS_DENIED`.
- `Bitrix\Iblock\Public\…` в основном проверяет данные REST, это не CRUD-фасад.

## REST V3 — Since iblock 26.100

iblock.element.get/list читают только инфоблок с REST_ON = Y и непустым API_CODE. Без API_CODE сущность не компилируется; REST_ON отдельно проверяется сервисом чтения. list требует фильтр iblockId, get — параметр iblock и id. Это не замена PHP-правил выше. Для старого модуля используй серверный сервис на подтверждённом PHP API; вызовы REST и официальный контракт — `bitrix24-rest-api`.
