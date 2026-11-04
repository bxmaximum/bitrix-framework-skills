# Шаблоны и поля подстановки

Шаблон — DOCX (`Body\Docx`, по умолчанию) либо текст (`Body\PlainText`) с полями `{Placeholder~modifiers}`, привязанный к модулю (`MODULE_ID`) и одному или нескольким поставщикам.

## Поля подстановки

- Каноническое имя — цепочка полей поставщика в CamelCase: `NUMBER` → `{Number}`, `CLIENT.NAME` → `{ClientName}`, `ITEMS.ITEM.NAME` → `{ItemsItemName}` (`DataProviderManager::valueToPlaceholder()`). Поля документа: `{DocumentNumber}`, `{DocumentTitle}`, `{DocumentCreateTime}`.
- Запись с точками `{SOURCE.NUMBER}` тоже работает: `SOURCE = Template::MAIN_PROVIDER_PLACEHOLDER`, `DOCUMENT = Template::DOCUMENT_PROVIDER_PLACEHOLDER`.
- Список полей поставщика: `DataProviderManager::getInstance()->getProviderPlaceholders(OrderProvider::class)`; компонент каталога: `bitrix:documentgenerator.placeholders`.
- **Since documentgenerator 26.200** подстановки DOCX заменяются только в текстовых узлах (`w:t`) и альтернативном тексте / имени изображения. В кодах полей и прочих атрибутах остаётся буквальное `{...}`.

## Модификаторы (`{Name~key=value,key2=value2}`)

| Модификатор | Применение |
| --- | --- |
| `format=d.m.Y` либо просто `~d.m.Y` | Дата: формат PHP для `FormatDate()`, не `DD.MM.YYYY` |
| `index=N`, `all` | Массив: один элемент / все элементы |
| `letterCase=lower\|upper\|title` | Строки |
| `mseparator=1\|2`, `mfirst=Y` | Множественные значения |
| `format=…`, `case=…` | Имена людей |

Значения `Y`/`N` преобразуются в булевы.

## Списки и таблицы

- Строка таблицы DOCX с полями элемента (`{ItemsItemName}`) повторяется для каждого элемента; отдельные маркеры не нужны.
- Другой повторяемый блок оберни в `{ITEMS.BLOCK_START}` … `{ITEMS.BLOCK_END}`: код массива без `SOURCE.`; конец не дальше 20 соседних узлов от начала.
- `ArrayDataProvider` добавляет `INDEX` (номер строки с 1) и `NUMBER` (число элементов).

## Изображения

Размести поле в альтернативном тексте (описании) или имени рисунка Word. Значение — путь внутри `/upload` (например, `\CFile::GetPath($fileId)`), путь временного файла либо URL http(s). Числовой ID `b_file` не работает. `TYPE`: `DataProvider::FIELD_TYPE_IMAGE` / `FIELD_TYPE_STAMP`.

## Создание шаблона кодом

Сервисного API нет: контроллер ядра записывает таблицы. Нужны DOCX, ID установленного модуля и зарегистрированный поставщик (`rules/providers.md`).

```php
$file = \CFile::MakeFileArray($absPathToDocx);
$file['isTemplate'] = true; // Disk: папка шаблонов
$fileResult = \Bitrix\DocumentGenerator\Model\FileTable::saveFile($file); // хранилище по умолчанию
if (!$fileResult->isSuccess()) { return $fileResult; }

$tplResult = \Bitrix\DocumentGenerator\Model\TemplateTable::add([
    'NAME' => 'Счёт заказа',                         // 1–100 символов
    'MODULE_ID' => 'vendor.orders',                  // совпадает с MODULE поставщика
    'FILE_ID' => $fileResult->getId(),               // b_documentgenerator_file.ID
    'BODY_TYPE' => \Bitrix\DocumentGenerator\Body\Docx::class,
    'REGION' => 'ru',
    'NUMERATOR_ID' => $numeratorId,                  // необязательно; rules/generation.md
]);
if (!$tplResult->isSuccess()) { return $tplResult; }
$templateId = $tplResult->getId();

\Bitrix\DocumentGenerator\Model\TemplateProviderTable::add(['TEMPLATE_ID' => $templateId, 'PROVIDER' => OrderProvider::class]);
\Bitrix\DocumentGenerator\Model\TemplateUserTable::add([
    'TEMPLATE_ID' => $templateId,
    'ACCESS_CODE' => \Bitrix\DocumentGenerator\Model\TemplateUserTable::ALL_USERS,
]);
```

- `TemplateUserTable` (`UA` — все, `U<id>`, `D<id>` и т. д.) определяет видимость шаблона в списках.
- При добавлении ядро нормализует DOCX (склеивает подстановки, разбитые Word) и проверяет размер файла.
- `TemplateTable::delete($id)` при наличии документов только помечает `IS_DELETED = 'Y'`; документы удалённого шаблона нельзя обновить.
- Чтение: `Template::loadById($id)` (без проверки прав), `$template->isDeleted()`, `$template->getFields()`.

## Даты и часовые пояса — Since documentgenerator 26.400

Пояс берётся из `TIMEZONE` шаблона, затем опции `document_timezone`, затем настроек PHP. Проверяй IANA-значение через `Bitrix\DocumentGenerator\Timezone::isValid()`; null в TIMEZONE возвращает наследование. Флаг `Feature\Timezone` включён по умолчанию.

Подстановки: `{DocumentCreateTime~d.m.Y,add=+10 days}`, `{DocumentCreateTime~d.m.Y H:i,timezone=Asia/Novosibirsk}`. У add обязательны знак, число до шести цифр и пробел; единицы year/month/day/hour/minute/second с необязательным s. Два add отменяют сдвиг; timezone ставь последним. Часы/минуты/секунды не применяются к чистой дате. Некорректный модификатор не прерывает генерацию: значение остаётся без сдвига / в унаследованном поясе. На старом модуле вычисли дату и пояс в поставщике, передай готовое значение.
