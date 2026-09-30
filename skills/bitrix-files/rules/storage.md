# b_file storage with CFile

A `b_file` row holds `ID`, `MODULE_ID`, `SUBDIR`, `FILE_NAME`, `ORIGINAL_NAME`, `CONTENT_TYPE`, `FILE_SIZE`, `WIDTH`, `HEIGHT`, `DESCRIPTION`, `EXTERNAL_ID` and `HANDLER_ID` (a cloud bucket, if any). The file lives at `/<option main:upload_dir>/<SUBDIR>/<FILE_NAME>` unless it is in a cloud. There is no D7 writer: `Bitrix\Main\FileTable` is for reads only (joins, reports).

## Save

```php
$file = \CFile::MakeFileArray($absPath);             // trusted local path
$file['MODULE_ID'] = 'vendor.docs';
$file['description'] = 'Contract scan';
$fileId = (int)\CFile::SaveFile($file, 'vendor.docs'); // /upload/vendor.docs/<3 hex>/<random>.<ext>
if ($fileId <= 0)
{
    // bad name, unsafe file, disk quota or write error
}
```

- Signature: `SaveFile($arFile, $strSavePath, $forceRandom = false, $skipExtension = false, $dirAdd = '')` → id or `false`.
- `$arFile` keys: `name`, and either `tmp_name` (copied) or `content` (raw bytes). Also `type`, `MODULE_ID`, `description`, `external_id`, and `old_file` (id deleted after a successful save).
- Default physical name: 32 random characters plus the original extension. `ORIGINAL_NAME` keeps the client's name with invalid characters replaced, so escape it on output.
- With option `save_original_file_name = Y` the physical name stays original inside a random subdir. `translit_original_file_name = Y` transliterates it. **Since main 26.800** only the physical name is transliterated (safe chars `._-()`); `ORIGINAL_NAME` never is. Older kernels transliterate both.
- `SaveFile()` strips script extensions (`php`, `phtml`, … from `fileman:~script_files`) and refuses names like `.htaccess`. It keeps `html`, `svg` and `js`, so whitelist extensions before saving user files (`rules/uploads.md`).
- It validates neither type nor size. `type` is stored as given (normalized), and an image with no readable dimensions is stored as `application/octet-stream`.
- Identical content is deduplicated (`b_file_hash`), so several IDs may share one physical file. `Delete()` keeps the file until its last ID is gone.
- Copy: `CFile::CloneFile(int $fileId): ?int` makes a new ID for the same physical file; `CFile::CopyFile($FILE_ID)` makes a physical copy.

## MakeFileArray

`CFile::MakeFileArray($path, $mimetype = false, $skipInternal = false, $external_id = '')` returns a `$_FILES`-like array. It accepts:

- **A b_file ID.** `tmp_name` is the stored file itself, or a temp copy for cloud files. Read it; never move or unlink it.
- **A local path**, absolute or relative to `DOCUMENT_ROOT`. Any readable file works, `/bitrix/.settings.php` included. MIME comes from `mime_content_type()`.
- **An `http(s)://` URL.** It is downloaded with `HttpClient`, and private IPs are blocked.
- **An `ftp(s)://` URL.** Private IPs are blocked only **Since main 26.800**; on older kernels accept only `https?://` from outside.

It returns `null` for a missing file, a directory, or `php://`/`phar://` (except `php://input`). Because it takes paths and URLs, user input turns it into arbitrary file read and SSRF. For user URLs, download with an allow-list (`bitrix-http-client`) and pass the temp file.

## Read

```php
$file = \CFile::GetFileArray($fileId);  // row + 'SRC' (cloud URL for bucket files) or false
$url = \CFile::GetPath($fileId);        // SRC or null
$thumb = \CFile::ResizeImageGet($fileId, ['width' => 300, 'height' => 300], BX_RESIZE_IMAGE_PROPORTIONAL, true);
// ['src' => …, 'width' => …, 'height' => …, 'size' => …] or false
```

- `ResizeImageGet($file, $arSize, $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL, $bInitSizes = false, $arFilters = false, $bImmediate = false, $jpgQuality = false)`.
- Resize types: `BX_RESIZE_IMAGE_PROPORTIONAL` (fit into the box), `BX_RESIZE_IMAGE_PROPORTIONAL_ALT` (fit; box width applies to the longer side), `BX_RESIZE_IMAGE_EXACT` (center crop).
- Pass both `width` and `height`: a zero side means no resizing. It never upscales: a size ≥ the original returns the original. The default filter is `sharpen`.
- The first call writes `/upload/resize_cache/<SUBDIR>/<w>_<h>_<type>/<FILE_NAME>` synchronously, so call it inside cached output. `CFile::Delete()` clears it.
- `CFile::GetByID()`/`GetFileArray()` follow content replacements (below); `FileTable` returns the raw row.

## Delete

```php
\CFile::Delete($fileId);  // DANGER: removes the file, its resize cache and versions; returns nothing
```

Delete files together with the entity that owns them (in your service or the ORM `OnAfterDelete`). Otherwise orphans stay forever.

## Replace content, keep the ID — Since main 26.800

```php
use Bitrix\Main\File\ContentReplacer;

$newId = (int)\CFile::SaveFile($newFileArray, 'vendor.docs');  // must be > $fileId
$result = (new ContentReplacer())->replace($fileId, $newId, $currentReplacementId, 'vendor.docs');
// $currentReplacementId: null the first time, then the previous $newId (optimistic lock)
```

- After this, `CFile::GetByID($fileId)` and `GetFileArray()` return the replacement (via `b_file_version`), and the superseded replacement is deleted. Result data: `['changed' => bool]`.
- Errors: `FILE_REPLACEMENT_INVALID`, `_CONFLICT`, `_LOCKED`, `_WRITE_FAILED`. Call it outside a DB transaction. `$owner` (`[a-zA-Z0-9_.-]{1,100}`) is a service tag, not an access check.
- On older kernels: save a new file and update the entity's file ID.

## Hooks and clouds

- Events (module `main`): `OnFileSave` (return `true` = stored externally), `OnAfterFileSave`, `OnMakeFileArray`, `OnGetFileSRC`, `OnBeforeResizeImage`, `OnAfterResizeImage`, `OnFileDelete`, `OnPhysicalFileDelete`.
- Module `clouds` uses these hooks. By bucket rules, files go to S3-compatible storage (`HANDLER_ID`), `SRC` becomes the bucket URL, and `MakeFileArray($id)` downloads a temp copy. Code built on the API above keeps working; code that builds `/upload/...` paths breaks.
