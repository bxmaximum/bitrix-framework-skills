# Generating documents

## From code

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
            return (new Result())->addError(new Error('Template not found'));
        }
        $template->setSourceType(OrderProvider::class);          // BEFORE getFields()/createByTemplate()
        if (!$template->getSourceType())
        {
            return (new Result())->addError(new Error('Provider is not registered for the template module'));
        }

        $document = Document::createByTemplate($template, $orderId);   // in memory, not saved yet
        if (!$document)
        {
            return (new Result())->addError(new Error('Cannot create document'));
        }
        $document->setUserId($userId);                                  // default: current user; 0 (CLI/agents) = no access check
        if (!$document->hasAccess())                                    // also turns on access checks for nested providers
        {
            return (new Result())->addError(new Error('Access denied'));
        }
        $document->setValues(['DocumentTitle' => 'Invoice ' . $orderId]); // optional overrides

        return $document->getFile(false);   // saves DOCX; true = also send to PDF/JPG conversion
    }
}
```

- Signatures: `Document::createByTemplate(Template $template, $value, array $data = []): ?Document`; `getFile(bool $sendToTransformation = true, bool $skipTransformationError = false): Result`; `hasAccess(int $userId = null): bool`.
- `getFile()` data: `id`, `number`, `title`, `downloadUrl`, `publicUrl`, `pdfUrl`/`imageUrl`/`printUrl` (when converted), `isTransformationError`, `pullTag`, `templateId`, `provider`, `values`, plus the provider's `getAdditionalDocumentInfo()`.
- Content: `Model\FileTable::getContent($document->FILE_ID)`; `b_file` id: `Model\FileTable::getBFileId($document->FILE_ID)`.
- Existing document: `Document::loadById($id)`, `$document->update($values)` (drops PDF/image), `$document->actualize($userId)` (re-read provider data), `enablePublicUrl()`, `getPublicUrl()`.
- Delete: `Bitrix\DocumentGenerator\Model\DocumentTable::delete($id)` (removes files and the public link; the `Document` class has no delete method).
- Preview without saving: `Bitrix\DocumentGenerator\VirtualDocument` (consumes a number too).

## PDF / JPG

- Conversion is asynchronous via module `transformer` (document server, queue `documentgenerator_create`, 20 min timeout). Without `transformer`, `getFile()` / `getFile(true)` returns an error although the DOCX is saved — use `getFile(false)` or `getFile(true, true)`.
- Completion: event `documentgenerator:onDocumentTransformationComplete` (`documentId`, `data`) and a pull event (`pullTag`, command `showImage`). Then reload: `Document::loadById($id)->PDF_ID`, `FileTable::getBFileId($pdfId)`.
- Conversion needs `b_file`-backed storage (default Disk or `BFile`).

## Numbers

- Template column `NUMERATOR_ID`; otherwise `Driver::getInstance()->getDefaultNumerator()`. Override per document with `setValues(['DocumentNumber' => 'A-15'])`.
- The next number is taken before field validation, so failed runs leave gaps.
- Create a numerator like the kernel does:

```php
$numerator = \Bitrix\Main\Numerator\Numerator::create();
$numerator->setConfig([
    \Bitrix\Main\Numerator\Numerator::getType() => [
        'name' => 'Invoices',
        'template' => '{NUMBER}',
        'type' => \Bitrix\DocumentGenerator\Driver::NUMERATOR_TYPE,
    ],
]);
$numeratorId = $numerator->save()->getId();
```

## Storage

`Driver::getInstance()->getDefaultStorage()`: class from option `documentgenerator`/`default_storage_type`, else `Storage\Disk` (disk installed, files in the "documents" storage), else `Storage\BFile`. Files are referenced by `b_documentgenerator_file.ID` (`FILE_ID`, `PDF_ID`, `IMAGE_ID`), never by `b_file.ID` directly. Custom storage: implement `Bitrix\DocumentGenerator\Storage` + `Nameable`, register via `onGetStorageTypeList`.

## Events (`documentgenerator`, D7 `Event`, param names)

| Event | Params |
| --- | --- |
| `onBeforeProcessDocument` | `document` |
| `onCreateDocument`, `onUpdateDocument` | `document` |
| `onDocumentTransformationComplete` | `documentId`, `data` |
| `onDeleteTemplate` | `templateId` (hard delete only) |

No document-delete event; use the ORM event of `DocumentTable` if needed.
