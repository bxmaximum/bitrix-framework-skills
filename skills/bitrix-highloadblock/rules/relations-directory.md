# Связи блоков и свойство-справочник инфоблока

## Тип UF `hlblock`

Хранит числовой ID целевой записи. `SETTINGS`, недопустимые значения заменяются стандартными:

| Ключ | По умолчанию | Назначение |
| --- | --- | --- |
| `HLBLOCK_ID` | 0 | Целевой блок, обязателен для связи |
| `HLFIELD_ID` | 0 | ID UF для подписи; 0 — `ID` |
| `DISPLAY` | `CUserTypeHlblock::DISPLAY_LIST` | `DISPLAY_CHECKBOX`, `DISPLAY_UI`, `DISPLAY_DIALOG` |
| `LIST_HEIGHT` | 1 одиночное / 5 множественное | Размер списка |
| `DEFAULT_VALUE` | `''` / `[]` | int / int[] |

`MULTIPLE` — свойство поля, а не `SETTINGS`. `DISPLAY_DIALOG` использует сущность селектора `highloadblock-element`: `highloadblockId`, `valueField`, `titleField`, `orderField`, `direction`, `queryMethod` (см. `bitrix-ui`).

- Одиночное поле автоматически создаёт `UF_X_REF`, только если задан `HLBLOCK_ID` и целевой блок существует. Компиляция источника компилирует и цель.

```php
$rows = $dataClass::getList([
    'select' => ['ID', 'CAT_NAME' => 'UF_CATEGORY_REF.UF_NAME'],
    'filter' => ['=UF_CATEGORY_REF.UF_ACTIVE' => 1],
])->fetchAll();
```

- Множественное поле не имеет `_REF`: значение — массив ID; `UF_X_SINGLE` фильтрует по одному (JOIN). Для загрузки целей собери ID, сделай один запрос `'@ID' => $ids`.
- Внешних ключей нет: существование цели не проверяется, оборванные ссылки не очищаются. Проверяй перед записью. При удалении цели выбери: запрет, очистка ссылок, замена запасным значением либо сохранение. Проверка с последующим удалением не атомарна. Проверяй права обоих блоков.
- Ссылка на себя (`UF_PARENT`, дерево) разрешена: запрещай самоссылки, обходи родителей с пределом глубины.
- Между средами находи цель по стабильному `UF_CODE` в целевой среде, сохраняй её локальный ID.

## Свойство инфоблока `directory` (справочник)

`CIBlockPropertyDirectory` находится в **highloadblock**, регистрируется на `OnIBlockPropertyBuildList` модуля iblock: загрузи оба модуля. Значение — строковый **`UF_XML_ID`**, а не ID записи HL.

```php
use Bitrix\Iblock\PropertyTable;

$property = new \CIBlockProperty();
$propId = $property->Add([
    'IBLOCK_ID' => $iblockId, 'NAME' => 'Цвет', 'CODE' => 'COLOR',
    'PROPERTY_TYPE' => PropertyTable::TYPE_STRING,
    'USER_TYPE' => PropertyTable::USER_TYPE_DIRECTORY,            // 'directory'
    'USER_TYPE_SETTINGS' => ['TABLE_NAME' => $hl['TABLE_NAME']], // точное TABLE_NAME блока
    'MULTIPLE' => 'N', // множественность здесь, не в настройках
]);
if (!$propId) { /* $property->LAST_ERROR */ }

\CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, ['COLOR' => 'red']); // результата нет: перечитай для проверки
```

- Поля справочника: `UF_XML_ID` (обязательный стабильный уникальный ключ, уникальность не проверяется), `UF_NAME`, `UF_SORT`, `UF_FILE`, `UF_DEF`, `UF_DESCRIPTION`, `UF_FULL_DESCRIPTION`, `UF_LINK`. Блок из интерфейса свойств получает `TABLE_NAME = b_hlbd_<name>` до 30 символов; API сохраняет заданное имя.
- Для вариантов нужен `UF_XML_ID`, иначе список пуст. Порядок: `UF_SORT`, `UF_NAME` (без него `UF_XML_ID`), `ID`. Без `UF_NAME` подпись — `UF_XML_ID`.
- Запись с `UF_DEF = 1` становится `DEFAULT_VALUE` свойства; первая по `UF_SORT`, `UF_NAME`.
- Преобразование: прочитай свойство → `$dataClass::getList(['filter' => ['@UF_XML_ID' => $values]])`. Так же проверяй входящие значения; порядок незначим.
- При переименовании `TABLE_NAME` прочитай полные `USER_TYPE_SETTINGS`, замени `TABLE_NAME`, вызови `CIBlockProperty::Update()`. Иначе варианты не загрузятся.
- Поиск по `TABLE_NAME` кешируется ORM на 86400 с и статически; `HighloadBlockTable::cleanCache()` сбрасывает статическую часть.
