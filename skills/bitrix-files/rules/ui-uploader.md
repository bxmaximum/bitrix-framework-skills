# ui.uploader: server side

The JS widget (`bitrix-ui`) calls the `ui.fileuploader.*` actions, which build your `UploaderController` from its name. Uploaded files become temp b_file rows, the form submits tokens, and your save code commits them.

```php
namespace Vendor\Docs\Uploader;   // /local/modules/vendor.docs/lib/Uploader/DocController.php

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Web\MimeType;
use Bitrix\UI\FileUploader\Configuration;
use Bitrix\UI\FileUploader\FileOwnershipCollection;
use Bitrix\UI\FileUploader\UploadResult;
use Bitrix\UI\FileUploader\UploaderController;
use Bitrix\UI\FileUploader\UploaderError;

final class DocController extends UploaderController
{
    private const MIME = ['application/pdf'];

    public function __construct(array $options)          // must be public; normalize options here
    {
        parent::__construct(['docId' => (int)($options['docId'] ?? 0)]);
    }

    public function isAvailable(): bool { return (int)CurrentUser::get()->getId() > 0; }
    public function getConfiguration(): Configuration
    {
        return (new Configuration())->setMaxFileSize(20 * 1024 * 1024)->setAcceptedFileTypes(['.pdf']);
    }
    public function canUpload() { return $this->canEdit(); }
    public function canView(): bool { return $this->canEdit(); }
    public function canRemove(): bool { return $this->canEdit(); }

    public function verifyFileOwner(FileOwnershipCollection $files): void
    {
        $own = $this->loadDocFileIds();                   // ids already bound to the doc
        foreach ($files as $file)
        {
            if (in_array($file->getId(), $own, true)) { $file->markAsOwn(); }
        }
    }

    public function onUploadComplete(UploadResult $uploadResult): void   // committed to b_file already
    {
        $fileId = $uploadResult->getFileInfo()?->getFileId();
        $path = $fileId ? (\CFile::MakeFileArray($fileId)['tmp_name'] ?? '') : '';
        if ($path === '' || !in_array(MimeType::getByFilePath($path), self::MIME, true))
        {
            $uploadResult->addError(new UploaderError(UploaderError::FILE_TYPE_NOT_ALLOWED)); // file is deleted
        }
    }

    private function canEdit(): bool { /* rights on docId */ return false; }
    private function loadDocFileIds(): array { return []; }
}
```

- JS name: `controller: 'vendor:docs.uploader.docController'`, with `controllerOptions: { docId: 42 }`. `vendor:module.path.class` maps to `\Vendor\Module\Path\Class`.
- The module is auto-included only if its `.settings.php` has `'ui.uploader' => ['value' => ['allowUseControllers' => true], 'readonly' => true]`.
- The class must live under `/local/modules/<id>/lib/`: the module id and the default `getCommitOptions()` (`moduleId`, `savePath`) come from its file path.
- The endpoint has no `Authentication` prefilter. `isAvailable()` and `can*()` are the only gates, and `preview`/`download` are GET without CSRF.

Commit when the entity is saved:

```php
use Bitrix\UI\FileUploader\Uploader;

$pending = (new Uploader(new DocController(['docId' => $docId])))->getPendingFiles($tokens);
$newFileIds = $pending->getFileIds();                 // only valid tokens
// ... bind $newFileIds to the doc, then:
$pending->makePersistent();                           // else temp files and their b_file rows are removed after ~1 day
```

- The form field mixes new tokens (`guid.signature` strings) with existing b_file IDs (numbers). Pass only the tokens to `getPendingFiles()`, and check numeric IDs against the entity: they are client input.
- A token is signed with the controller name, its options and `bitrix_sessid()`. Commit in the same session with identical, normalized options; otherwise the pending file gets an error (`$pendingFile->isValid()`, `getErrors()`).
- `Configuration` checks accepted types by extension or client MIME and decodes only images, hence the content check in `onUploadComplete()`.
- That check is mandatory with presigned uploads (**Since ui 26.600**, `ui` → `uploader.settings`, see `bitrix-settings`). The bytes bypass PHP, so `canUpload()` sees only client-declared data.
- Extend only `UploaderController`. Don't subclass `Uploader` or `Controller\FileUploader` (signatures changed in ui 26.600).
