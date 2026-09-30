# Serving files

## Public vs private

- The web server serves `SRC` under `/upload/` with no Bitrix checks. Random names hide files but don't protect them.
- Private files: never output `SRC`. Serve them from an action that checks rights and returns `BFile`. To close the direct path too, deny web access to `/upload/<your savePath>/` in nginx/Apache; `ResizeImageGet()` URLs there then stop working.

```php
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Engine\Response\BFile;

public function downloadAction(int $docId, DocFileService $files): ?BFile
{
    $fileId = $files->findReadableFileId($docId, (int)CurrentUser::get()->getId());
    if ($fileId === null)
    {
        $this->addError(new \Bitrix\Main\Error('Access denied', 'ACCESS_DENIED'));
        return null;
    }

    return BFile::createByFileId($fileId);   // throws ObjectNotFoundException for an unknown id
}
```

- A GET link to this action needs its CSRF prefilter removed; keep `Authentication` and the rights check (`bitrix-controllers`).
- `BFile::createByFileId($fileId, $name = null)`: `->showInline(true)` shows only valid images inline, and everything else is always downloaded (`CFile::ViewByUser()` enforces this). `->setCacheTime($seconds)`.
- Thumbnails: `ResizedImage::createByImageId($imageId, $width, $height)` (default `BX_RESIZE_IMAGE_EXACT`, `->setResizeType()`).
- `CFile::ViewByUser($arFile, ['force_download' => true, 'attachment_name' => $name, 'cache_time' => 0])` is the legacy engine under `BFile`. Use it only in non-controller scripts.
- Big files: with option `main:bx_fast_download = Y`, `ViewByUser()` sends `X-Accel-Redirect`, and nginx must serve that path internally.
- `ORIGINAL_NAME` is client input: wrap it in `htmlspecialcharsbx()` in HTML. `BFile` strips CR/LF from the download name.

## Viewer

- `ui.viewer` (`bitrix-ui`) opens the file URL and sends preview requests (`BX-Viewer*` headers). `ViewByUser()`/`BFile` answer those, and a hand-written `readfile()` breaks them.
- Markup: echo `\Bitrix\Main\UI\Viewer\ItemAttributes::tryBuildByFileId($fileId, $downloadUrl)` into the link tag.
- The viewer checks no rights. The action behind `$downloadUrl` must.
