# Генерация документов

## Из кода

```php
<?php
declare(strict_types=1);

namespace Vendor\Orders\Service;

use Bitrix\DocumentGenerator\Document;
use Bitrix\DocumentGenerator\Template;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use Vendor\Orders\DocumentGenerator\OrderProvider;

final class OrderDocumentService
{
    public function generate(int $templateId, int $orderId, int $userId): Result
    {
        Loader::requireModule('documentgenerator');
        $template = Template::loadById($templateId);
        if (!$template || $template->isDeleted())
        {
            return (new Result())->addError(new Error('Шаблон не найден'));
        }
        $template->setSourceType(OrderProvider::class);          // ДО getFields()/createByTemplate()
        if (!$template->getSourceType())
        {
            return (new Result())->addError(new Error('Поставщик не зарегистрирован для модуля шаблона'));
        }

        $document = Document::createByTemplate($template, $orderId); // пока в памяти, не сохранён
        if (!$document)
        {
            return (new Result())->addError(new Error('Не удалось создать документ'));
        }
        $document->setUserId($userId); // по умолчанию текущий пользователь; 0 (CLI/агенты) отключает проверку доступа
        if (!$document->hasAccess()) // включает также проверки вложенных поставщиков
        {
            return (new Result())->addError(new Error('Доступ запрещён'));
        }
        $document->setValues(['DocumentTitle' => 'Счёт ' . $orderId]); // необязательная подмена

        return $document->getFile(false); // сохраняет DOCX; true также запускает преобразование PDF/JPG
    }
}
```

- Сигнатуры: `Document::createByTemplate(Template $template, $value, array $data = []): ?Document`; `getFile(bool $sendToTransformation = true, bool $skipTransformationError = false): Result`; `hasAccess(int $userId = null): bool`.
- Данные `getFile()`: `id`, `number`, `title`, `downloadUrl`, `publicUrl`, `pdfUrl`/`imageUrl`/`printUrl` (после преобразования), `isTransformationError`, `pullTag`, `templateId`, `provider`, `values` и результат `getAdditionalDocumentInfo()` поставщика.
- Содержимое: `Model\FileTable::getContent($document->FILE_ID)`; ID `b_file`: `Model\FileTable::getBFileId($document->FILE_ID)`.
- Сохранённый документ: `Document::loadById($id)`, `$document->update($values)` (пересобирает файл и запускает выбранное преобразование), `enablePublicUrl()`, `getPublicUrl()`. **Since documentgenerator 26.400** `Document::actualize()` удалён: задай пользователя через `setUserId()`, проверь `hasAccess()`, вызови `update([])`. На старом модуле `actualize($userId)` ещё доступен. <!-- verify:negative -->
- Удаление: `Bitrix\DocumentGenerator\Model\DocumentTable::delete($id)` удаляет файлы и публичную ссылку. У класса `Document` метода удаления нет.
- Предпросмотр без сохранения: `Bitrix\DocumentGenerator\VirtualDocument`; тоже расходует номер.

## PDF / JPG

- Преобразование асинхронное через `transformer`: сервер документов, очередь `documentgenerator_create`, таймаут 20 минут. Без `transformer` вызов `getFile()` / `getFile(true)` возвращает ошибку, хотя DOCX сохранён. Используй `getFile(false)` либо `getFile(true, true)`.
- Завершение: событие `documentgenerator:onDocumentTransformationComplete` (`documentId`, `data`) и Pull (`pullTag`, команда `showImage`). Затем перечитай `Document::loadById($id)->PDF_ID`, `FileTable::getBFileId($pdfId)`.
- Преобразованию нужно хранилище на базе `b_file` (обычно Disk или `BFile`).

## Нумерация

- `NUMERATOR_ID` шаблона, иначе `Driver::getInstance()->getDefaultNumerator()`. Подмена номера документа: `setValues(['DocumentNumber' => 'A-15'])`.
- Следующий номер берётся до проверки полей; неудачные запуски оставляют пропуски.
- Создавай нумератор как ядро:

```php
$numerator = \Bitrix\Main\Numerator\Numerator::create();
$numerator->setConfig([
    \Bitrix\Main\Numerator\Numerator::getType() => [
        'name' => 'Счета',
        'template' => '{NUMBER}',
        'type' => \Bitrix\DocumentGenerator\Driver::NUMERATOR_TYPE,
    ],
]);
$numeratorId = $numerator->save()->getId();
```

## Хранение

`Driver::getInstance()->getDefaultStorage()` берёт класс из опции `documentgenerator`/`default_storage_type`; иначе `Storage\Disk` (модуль disk, хранилище «documents»), иначе `Storage\BFile`. Ссылки на файлы — `b_documentgenerator_file.ID` (`FILE_ID`, `PDF_ID`, `IMAGE_ID`), а не напрямую `b_file.ID`. Своё хранилище: реализуй `Bitrix\DocumentGenerator\Storage` + `Nameable`, зарегистрируй через `onGetStorageTypeList`.

## События (`documentgenerator`, D7 `Event`, имена параметров)

| Событие | Параметры |
| --- | --- |
| `onBeforeProcessDocument` | `document` |
| `onCreateDocument`, `onUpdateDocument` | `document` |
| `onDocumentTransformationComplete` | `documentId`, `data` |
| `onDeleteTemplate` | `templateId` (только физическое удаление) |

События удаления документа нет; при необходимости используй ORM-событие `DocumentTable`.
