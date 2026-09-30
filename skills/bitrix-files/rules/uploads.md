# Accepting uploads

## Own action (multipart form)

Files are never bound to action parameters. Read them from the request and pass them to a service (`bitrix-controllers`; default prefilters keep CSRF, so send `sessid`).

```php
public function uploadAction(int $docId, DocFileService $files): ?array
{
    $result = $files->store($docId, (array)$this->getRequest()->getFile('file'));
    if (!$result->isSuccess())
    {
        $this->addErrors($result->getErrors());
        return null;
    }
    return $result->getData();
}
```

```php
use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Web\MimeType;

public function store(int $docId, array $file): Result
{
    $result = new Result();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? ''))
    {
        return $result->addError(new Error('No file uploaded', 'NO_FILE'));
    }

    $file['type'] = MimeType::getByFilePath($file['tmp_name']);  // magic bytes, not the client header
    $error = \CFile::CheckFile($file, 10 * 1024 * 1024, ['application/pdf'], 'pdf');
    if ($error !== '')
    {
        return $result->addError(new Error($error, 'BAD_FILE'));
    }

    $file['MODULE_ID'] = 'vendor.docs';
    $fileId = (int)\CFile::SaveFile($file, 'vendor.docs');
    if ($fileId <= 0)
    {
        return $result->addError(new Error('Cannot save file', 'SAVE_FAILED'));
    }
    // bind $fileId to $docId in your table here
    return $result->setData(['fileId' => $fileId]);
}
```

- `is_uploaded_file()` blocks a forged array (`tmp_name` = `/etc/passwd`): `SaveFile()` copies any readable path.
- `CFile::CheckFile($arFile, $intMaxSize = 0, $mimeType = false, $strExt = false, $bForceMD5 = false, $bSkipExt = false)` returns `''` on success, and also for an empty `name`, so check `error` first. `$mimeType` is a list of prefixes matched against `$arFile['type']`; `$strExt` is a comma-separated list.
- Images: `CFile::CheckImageFile($arFile, $iMaxSize = 0, $iMaxWidth = 0, $iMaxHeight = 0)` decodes the image. It returns `null` on success and an error string otherwise, so test `(string)$error !== ''`.
- Detect content with `MimeType::getByFilePath()` or `getByContent()` (finfo). Image check: `(new \Bitrix\Main\File\Image($path))->getInfo()` returns `Image\Info` or `null`.
- Always whitelist extensions. An `html`, `htm`, `svg`, `xml` or `js` file saved to `/upload/` runs in your origin when opened: stored XSS.
- `name="files[]"` arrives in PHP's per-key layout (`name[0]`, `tmp_name[0]`, …). For big files use `ui.uploader` (chunked, `rules/ui-uploader.md`) instead of raising PHP limits.

## Legacy `bitrix:main.file.input` (compat only)

It saves every file to b_file immediately. On submit, keep only the IDs registered for the control: `\Bitrix\Main\UI\FileInputUtility::instance()->checkFiles($controlId, $ids)`, where `$controlId` is `CONTROL_ID` (default `'mfi' . INPUT_NAME`). Without this, any b_file ID can be attached (IDOR).
