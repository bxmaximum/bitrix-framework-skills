# Свойства, разделы, элементы

## Свойства

```php
use Bitrix\Iblock\PropertyTable;

$property = new \CIBlockProperty();
$propId = $property->Add([
    'IBLOCK_ID'     => $iblockId,
    'NAME'          => 'Источник',
    'CODE'          => 'SOURCE',                  // обязателен: ORM пропускает свойства без CODE
    'PROPERTY_TYPE' => PropertyTable::TYPE_LIST,  // S, N, F, L, E (элемент), G (раздел)
    'MULTIPLE'      => 'N',
]);
if (!$propId) { throw new \RuntimeException($property->LAST_ERROR); }

$enumId = (new \CIBlockPropertyEnum())->Add([
    'PROPERTY_ID' => $propId, 'VALUE' => 'Reuters', 'XML_ID' => 'reuters', 'SORT' => 10,
]);
```

Пользовательские типы (`PROPERTY_TYPE` + `USER_TYPE`, константы `PropertyTable`):

- `S` + `USER_TYPE_HTML` — HTML-редактор; `S` + `USER_TYPE_DATE` / `USER_TYPE_DATETIME` — дата.
- `E` + `USER_TYPE_SKU` — связь SKU (`bitrix-catalog`).
- `S` + `USER_TYPE_DIRECTORY` (`directory`) — HL-справочник. `USER_TYPE_SETTINGS => ['TABLE_NAME' => $hl['TABLE_NAME']]` точно совпадает с `TABLE_NAME` блока (`b_hlbd_*` только для блоков интерфейса свойств). Хранит строку `UF_XML_ID`; множественность — `MULTIPLE` свойства. Класс типа — в highloadblock: `bitrix-highloadblock`, `rules/relations-directory.md`.
- Настройки своих типов: массив скаляров `USER_TYPE_SETTINGS`. **Since iblock 26.0.100** `PropertyTable` читает `USER_TYPE_SETTINGS_LIST` с `allowed_classes => false`: сохранённый объект становится `__PHP_Incomplete_Class`; классический `CIBlockProperty*` уже так делал. Создавай DTO после чтения.

## Разделы через ORM

```php
use Bitrix\Iblock\Model\Section;

$sectionClass = Section::compileEntityByIblock('News'); // '\Bitrix\Iblock\Section{ID}Table'

$parent = $sectionClass::createObject()
    ->setIblockId($iblockId)
    ->setName('События')
    ->setCode('events')
    ->set('UF_MANAGER', 'Иван Иванов') // UF раздела через set('UF_*', …)
    ->save();
if (!$parent->isSuccess()) { /* $parent->getErrorMessages() */ }

$sectionClass::createObject()
    ->setIblockId($iblockId)
    ->setName('Выставки')
    ->setCode('exhibitions')
    ->setIblockSectionId($parent->getId())
    ->save();

$section = $sectionClass::query()
    ->setSelect(['ID', 'NAME', 'PARENT_SECTION', 'UF_*'])
    ->where('CODE', 'exhibitions')
    ->fetchObject();
$section?->getParentSection()?->getName();
```

Удаляй через `CIBlockSection::Delete($id)` каскадно; ORM оставляет потомков без родителя (`rules/orm-entities.md`).

## Элементы через ORM

```php
use Bitrix\Iblock\Elements\ElementNewsTable;
use Bitrix\Iblock\ORM\PropertyValue;

$element = ElementNewsTable::createObject()
    ->setName('Обновление безопасности')
    ->setCode('security-update')
    ->setActive(true)
    ->setIblockSectionId($sectionId)                        // синхронизирует SECTIONS
    ->set('AUTHOR', 'Анна Иванова')                         // S
    ->set('SOURCE', $enumId)                                // L: ID варианта списка
    ->set('RELATED', $otherElementId)                       // E: ID элемента
    ->set('PHOTO', new PropertyValue($fileId, 'Главное фото')) // F: ID файла и описание
    ->addTo('TAGS', 'security')                             // множественное
    ->addTo('TAGS', '2026');

$result = $element->save();
if (!$result->isSuccess()) { /* $result->getErrorMessages() */ }

$element->removeFrom('TAGS', 'security'); // по значению
$element->removeAll('TAGS');
```

- Скаляры подходят для любого свойства; `PropertyValue($value, $description)` нужен для описания.
- Файл сначала сохрани: `$fileId = \CFile::SaveFile(\CFile::MakeFileArray($path), 'iblock')`, затем установи ID.
- `addTo()` пропускает дубли `L/E/G/F`, разрешает для `S/N`.

## Символьный код (`CODE`)

ORM не генерирует `CODE`. `(new \CIBlockElement())->generateMnemonicCode($name, $iblockId)` (также `CIBlockSection`, `null` при отключённой транслитерации) использует настройки `CODE` и `CUtil::translit`. **Since main 26.800** конечная пунктуация оставляет разделитель: `Скидка 10%` → `skidka_10_`. Старые коды не меняются, новые отличаются. Для стабильности — `rtrim($code, '_')` с учётом своего `TRANS_OTHER`, затем проверка уникальности.
