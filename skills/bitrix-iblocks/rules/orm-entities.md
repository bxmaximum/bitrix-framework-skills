# Компилируемые классы ORM

## Элементы

`\Bitrix\Iblock\Elements\Element{ucfirst(API_CODE)}Table`: `API_CODE = News` → `ElementNewsTable`. Автозагрузка при первом обращении (`Bitrix\Iblock\ORM\Loader`), можно сразу `ElementNewsTable::query()`. Динамическое получение:

```php
use Bitrix\Iblock\Iblock;
use Bitrix\Iblock\IblockTable;

$elementClass = Iblock::wakeUp($iblockId)->getEntityDataClass(); // без API_CODE: null и E_USER_WARNING
$entity = IblockTable::compileEntity('News'); // ElementEntity|false, принимает API_CODE либо Iblock
$elementClass = $entity ? $entity->getDataClass() : null;
```

- Родитель: `ORM\ElementV1Table` / `ORM\ElementV2Table` по версии хранения, оба наследуют `ORM\CommonElementTable`. `IblockTable::compileAllEntities()` компилирует все инфоблоки с `API_CODE`.
- `SECTIONS`: ManyToMany с `SectionTable` через `SectionElementTable`, только прямые связи. `setIblockSectionId()` синхронизирует с `IBLOCK_SECTION_ID`.
- `Bitrix\Iblock\ElementTable` знает базовые поля; `add/update/delete` **всегда возвращают ошибку** (`ELEMENT_ENTITY_MESS_*_BLOCKED`). Используй только для чтения базовых полей.
- Не переопределяй `getMap()` наследника `Element{ApiCode}Table`: побеждает скомпилированная карта. Используй `runtime`/`registerRuntimeField()` запроса, без имён системных полей и кодов свойств.

## Разделы

`Model\Section::compileEntityByIblock($iblock)` принимает ID, `API_CODE` или `Iblock`; возвращает FQCN runtime-класса `Section<IBLOCK_ID>Table` в `Bitrix\Iblock` либо `null`. Добавляет `PARENT_SECTION`, UF-сущность `IBLOCK_{ID}_SECTION` (`UF_*`) и стандартное ограничение `IBLOCK_ID`.

## Значения свойств

Свойство с `CODE` становится одноимённым полем; без кода пропускается. Одиночное — связь, множественное — коллекция объектов значений. Сущность `\Bitrix\Iblock\Elements\IblockProperty{PROPERTY_ID}Table`: `VALUE`, также `DESCRIPTION`, если разрешено.

| Тип | `VALUE` | Автоматическая связь |
| --- | --- | --- |
| `S` | string | — |
| `N` | float | — |
| `F` | int | `FILE` (`b_file`) |
| `L` | int | `ITEM` (`PropertyEnumerationTable`: `VALUE`, `XML_ID`) |
| `E` | int | `ELEMENT` (только при `API_CODE` связанного инфоблока) |
| `G` | int | `SECTION` (`SectionTable`) |

Свой тип может добавить поля обработчиком `GetORMFields`.

## Побочные действия ORM-записи

Компилируемые классы элементов **выполняют** проверку обязательных свойств (`onBeforeAdd`), очистку тегов инфоблока (`CIBlock::clearIblockTagCache`), обновление / удаление фасетного индекса, удаление значений свойств, создание одиночной строки v2. Классы разделов очищают теги и пересчитывают дерево (`LEFT/RIGHT_MARGIN`, `DEPTH_LEVEL`, `GLOBAL_ACTIVE`). Документация предлагает делать вручную, но ядро уже выполняет это.

ORM **не выполняет**: индексацию поиска, пересчёт наследуемых SEO-свойств, проверку / установку прав, документооборот / бизнес-процессы, уменьшение `PREVIEW/DETAIL_PICTURE`, авто-`CODE`, квоту диска, пересчёт доступности каталога / цен SKU, журнал инфоблока, привязку свойств раздела, классические `OnBeforeIBlockElement*`/`OnAfterIBlockElement*`. Если нужно — `CIBlockElement::Add/Update/Delete`, `CIBlockSection`.

`$object->delete()` раздела удаляет только его, оставляя дочерние разделы и элементы без родителя. `CIBlockSection::Delete($id)` удаляет каскадно, чистит поиск и кеш.
