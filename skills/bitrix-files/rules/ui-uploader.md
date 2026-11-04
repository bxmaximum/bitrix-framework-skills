# ui.uploader: серверная часть

JS-виджет (`bitrix-ui`) вызывает `ui.fileuploader.*`, создающие `UploaderController` по имени. Загрузки — временные строки b_file; форма отправляет токены, код сохранения закрепляет файлы.

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

    public function __construct(array $options) // публичный; нормализуй настройки здесь
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
        $own = $this->loadDocFileIds(); // ID файлов, уже привязанных к документу
        foreach ($files as $file)
        {
            if (in_array($file->getId(), $own, true)) { $file->markAsOwn(); }
        }
    }

    public function onUploadComplete(UploadResult $uploadResult): void // уже сохранено в b_file
    {
        $fileId = $uploadResult->getFileInfo()?->getFileId();
        $path = $fileId ? (\CFile::MakeFileArray($fileId)['tmp_name'] ?? '') : '';
        if ($path === '' || !in_array(MimeType::getByFilePath($path), self::MIME, true))
        {
            $uploadResult->addError(new UploaderError(UploaderError::FILE_TYPE_NOT_ALLOWED)); // файл удаляется
        }
    }

    private function canEdit(): bool { /* rights on docId */ return false; }
    private function loadDocFileIds(): array { return []; }
}
```

- Имя в JS: `controller: 'vendor:docs.uploader.docController'`, `controllerOptions: { docId: 42 }`. `vendor:module.path.class` соответствует `\Vendor\Module\Path\Class`.
- Модуль подключается автоматически, только если в его `.settings.php` есть `'ui.uploader' => ['value' => ['allowUseControllers' => true], 'readonly' => true]`.
- Класс размещай в `/local/modules/<id>/lib/`: по пути определяются ID модуля и стандартные `getCommitOptions()` (`moduleId`, `savePath`).
- У точки входа нет фильтра `Authentication`. Защита — `isAvailable()` и `can*()`; `preview`/`download` доступны GET без CSRF.

Закрепляй при сохранении сущности:

```php
use Bitrix\UI\FileUploader\Uploader;

$pending = (new Uploader(new DocController(['docId' => $docId])))->getPendingFiles($tokens);
$newFileIds = $pending->getFileIds(); // только корректные токены
// привяжи $newFileIds к документу, затем:
$pending->makePersistent(); // иначе временные файлы и строки b_file удалятся примерно через сутки
```

- Поле формы смешивает новые токены (`guid.signature`) и существующие числовые ID b_file. В `getPendingFiles()` передавай только токены, ID проверяй на принадлежность сущности: они приходят от клиента.
- Токен подписан именем контроллера, настройками и `bitrix_sessid()`. Закрепляй в той же сессии с идентичными нормализованными настройками; иначе у файла ошибка (`$pendingFile->isValid()`, `getErrors()`).
- `Configuration` проверяет расширение либо MIME клиента, декодирует только изображения. Поэтому нужна проверка содержимого в `onUploadComplete()`.
- Она обязательна для загрузок по подписанному URL (**Since ui 26.600**, `ui` → `uploader.settings`, см. `bitrix-settings`): байты обходят PHP, `canUpload()` видит только данные клиента.
- Наследуй только `UploaderController`, а не `Uploader` / `Controller\FileUploader`: сигнатуры изменились в ui 26.600.
