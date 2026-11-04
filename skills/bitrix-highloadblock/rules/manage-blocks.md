# Управление блоками, полями и хранением

## Имена и ограничения

- `NAME`: `^[A-Z][A-Za-z0-9]*$`, до 100 символов, не заканчивается `Table`. `Collection` в любом регистре запрещён **Since highloadblock 25.100\***; на старом ядре избегай сам.
- `TABLE_NAME`: `^[a-z0-9_]+$`, до 64 символов. Оба уникальны; таблица ещё не должна существовать.
- `HighloadBlockLangTable`: `LID` до 2, `NAME` до 100. `HighloadBlockRightsTable.ACCESS_CODE` до 50.

## Создание, поиск, изменение

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

- `add()` создаёт таблицу с BIGINT `ID`. При ошибке `CREATE TABLE` удаляет строку описания и возвращает ошибку **Since highloadblock 25.100\***. Раньше бросает исключение и оставляет строку: удали перед повтором.
- Поиск без исключения: `HighloadBlockTable::resolveHighloadblock($idOrName): ?array` (int / строка с цифры — ID, другая строка — `NAME`). Поиск ID/NAME кешируется ORM на 86400 с **Since highloadblock 25.100\***. Кеш устаревает только при обходе ORM в `b_hlblock_entity`.
- Объектный API: `HighloadBlockTable::getById($id)->fetchObject()?->getEntityDataClass()`. Данные блока: `$dataClass::getHighloadBlock()['ID']`.
- Дополнительные поля `getList`: `FIELDS_COUNT`, `'LANG_NAME' => 'LANG.NAME'`. Названия: `HighloadBlockLangTable::add(['ID' => $hlId, 'LID' => 'ru', 'NAME' => '…'])`.
- Новый `NAME` в `update()` меняет класс и имена событий; старые обработчики теряются. `TABLE_NAME` меняет имя основной и множественных таблиц; обнови `TABLE_NAME` каждого свойства-справочника (`rules/relations-directory.md`).
- `delete()` удаляет записи, файлы, UF, языковые строки, права и таблицы. Необратимо: сначала резервная копия. Реестр UF удаляется прямым SQL, без `OnBeforeUserTypeDelete`/`OnAfterUserTypeDelete`.
- `HighloadBlockTable::cleanCache()` также сбрасывает статические кеши `CIBlockPropertyDirectory`.

## Пользовательские поля

Создавай через `CUserTypeEntity`, `ENTITY_ID = HighloadBlockTable::compileEntityId($hlId)` (`HLBLOCK_7`).

- `FIELD_NAME`: `UF_` + заглавная латиница / цифры / `_`, 4–50 символов. Суффикс `_REF` у `HLBLOCK_*` запрещён: зарезервирован для связей. Ошибка в `$APPLICATION->GetException()`.
- Типы `USER_TYPE_ID`: `string`, `integer`, `double`, `boolean`, `datetime`, `date`, `file`, `enumeration`, `hlblock`, …; `rich_text` (BBCode, ui) **Since ui 26.600**. На старом ядре `string` со своей обработкой BBCode.
- По умолчанию `Add()`: `SORT` 100, `MULTIPLE`/`MANDATORY` `N`, `SHOW_IN_LIST` `Y` (колонки `highloadblock.list`), `EDIT_IN_LIST` `Y`, `IS_SEARCHABLE` `N`, `SHOW_FILTER` `N` (`N|I|E|S` — нет / точное / маска / подстрока).

```php
$entityId = HighloadBlockTable::compileEntityId($hlId);
$existing = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_CODE'])->Fetch();
if (!$existing) {
    $id = (new \CUserTypeEntity())->Add([
        'ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_CODE', 'USER_TYPE_ID' => 'string', 'MANDATORY' => 'Y',
    ]);
    // $id === false → $APPLICATION->GetException()?->GetString()
} elseif ($existing['USER_TYPE_ID'] !== 'string' || $existing['MULTIPLE'] !== 'N') {
    throw new \RuntimeException('UF_CODE существует с другим типом'); // не переиспользуй молча
}
```

- `Update()` не меняет `ENTITY_ID`, `FIELD_NAME`, `USER_TYPE_ID`, `MULTIPLE`: создай новое поле, перенеси значения, удали старое. Обновление подписей заменяет все сохранённые подписи.
- `Delete()` удаляет данные поля и файлы. **Since main 26.1000** в стандартном UF-хранилище удаление столбца может быть отложено агентом. У highloadblock собственные обработчики хранения: проверяй результат CUserTypeEntity, не считай физический DROP гарантированно немедленным. На старом ядре структура меняется прежним обработчиком удаления.

## Хранение

- `TABLE_NAME`: `ID` и столбец на одиночное UF (`ALTER TABLE ADD` при добавлении). `string` хранится как `TEXT`, не varchar; `integer`/`boolean`/`hlblock` — int.
- Множественное UF: `<TABLE_NAME>_<lowercase field>` (`HighloadBlockTable::getMultipleValueTableName()`), `ID`, `VALUE`, индексы `IX_UTM_HL<hlId>_<ufId>_ID/_VALUE`; класс ORM `<Name>Utm<CamelField>`. Основная таблица хранит сериализованную копию. `DataManager` синхронизирует обе.

## Перекомпиляция

Первый `compileEntity()` запроса создаёт класс, остальные возвращают его. После изменения UF используй новый запрос. В том же запросе: перечитай строку, `compileEntity($row, true)` пересоберёт карту, но не переопределит PHP-класс; проверь `$entity->hasField('UF_X')`. Изменения `NAME`/`TABLE_NAME` всегда требуют нового запроса.

## Миграции и перенос

- Используй `sprint.migration`, помощник `Hlblock` (`bitrix-sprint-migration`). Порядок: найти / создать по `NAME` → UF по `ENTITY_ID` + `FIELD_NAME` (добавить отсутствующие, ошибка при несовпадении типа) → скопировать значения до замены поля → записи в новом запросе. Останавливайся на первой ошибке; не запускай одну миграцию параллельно.
- После: `getById()` для `NAME`/`TABLE_NAME`, `CUserTypeEntity::GetList([], ['ENTITY_ID' => …])` для кодов / типов / `MULTIPLE`, поля `compileEntity()`, пробное добавление и чтение.
- XML-экспорт / импорт — только административные `/bitrix/admin/highloadblock_export.php`, `highloadblock_import.php`, PHP API нет. При импорте выбери внешний ключ (ID либо строковое / целое UF); без него все строки добавятся дублями. Переноси XML вместе с соседним каталогом файлов.
