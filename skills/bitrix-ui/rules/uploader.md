# File upload fields

New code: `ui.uploader` widgets backed by your `UploaderController`. Legacy `main` uploaders are for existing forms only. The server side (controller class, rights, committing pending files, content checks) lives in `bitrix-files`.

## `ui.uploader`

| Extension | Export (global `BX.UI.Uploader`) |
| --- | --- |
| `ui.uploader.tile-widget` | `TileWidget`: tiles with previews |
| `ui.uploader.stack-widget` | `StackWidget`, `StackWidgetSize` (`LARGE`, `MEDIUM`, `SMALL`, `TINY`): compact |
| `ui.uploader.core` | `Uploader`, `UploaderEvent`, `FileStatus`, `FileEvent`: headless, your own markup |
| `ui.uploader.vue` | Vue integration (`VueUploaderWidget` base) |

```js
import { TileWidget } from 'ui.uploader.tile-widget';

const widget = new TileWidget({
    controller: 'vendor:orders.fileUploader.docController', // → \Vendor\Orders\FileUploader\DocController
    controllerOptions: { orderId: 42 },      // client data: the controller must validate it
    files: savedFileIds,                     // numbers: loaded through canView() + verifyFileOwner()
    hiddenFieldsContainer: form,
    hiddenFieldName: 'DOCS',                 // DOCS[] when multiple: tokens (new) and ids (existing)
    multiple: true,
    maxFileSize: 10 * 1024 * 1024,           // UX only: the server enforces getConfiguration()
    acceptedFileTypes: ['.pdf', '.docx'],
    events: { [UploaderEvent.FILE_UPLOAD_COMPLETE]: (event) => {} }, // import UploaderEvent from ui.uploader.core
});
widget.renderTo(node);
const uploader = widget.getUploader();       // core Uploader: getFiles(), removeFiles(), destroy()
```

- `new StackWidget(uploaderOptions, { size: StackWidgetSize.MEDIUM })`; `TileWidget` takes widget options as the second argument too.
- Controller name → class: `vendor:module.path.className` → `\Vendor\Module\Path\ClassName`. The module must allow it in its `.settings.php` (`'ui.uploader' => ['value' => ['allowUseControllers' => true], 'readonly' => true]`).
- Events: `File:onAdd`, `File:onRemove`, `File:onUploadComplete`, `File:onError`, `onUploadComplete`, `onError`, `onMaxFileCountExceeded` (`UploaderEvent.*`).
- A new file gets its hidden input only after its upload completes: block submit while files are still uploading.
- Hidden fields carry tokens for new files and b_file ids for kept ones. Commit tokens and re-check ids on save (`bitrix-files`).
- Parallel and presigned chunk upload (`ParallelUploadController`, `PresignedUploadController`) are **Since ui 26.600** and off by default (`bitrix-settings`).

## Legacy (`main`), compat only

- Component `bitrix:main.file.input`: `INPUT_NAME` (required, `[a-zA-Z0-9_]`), `INPUT_VALUE` (file ids), `MULTIPLE`, `ALLOW_UPLOAD` (`A|I|F|N`), `ALLOW_UPLOAD_EXT`, `MAX_FILE_SIZE`, `MODULE_ID`, `CONTROL_ID` (default `'mfi' . INPUT_NAME`). On submit filter ids with `FileInputUtility::instance()->checkFiles($controlId, $ids)` (`bitrix-files`).
- Admin forms: `Bitrix\Main\UI\FileInput::createInstance(['name' => 'FILE[n#IND#]', 'upload' => true, 'allowUpload' => FileInput::UPLOAD_IMAGES, 'maxCount' => 5])->show($fileIds)`.
- Own receiver `Bitrix\Main\UI\Uploader\Uploader` (`checkPost()`): file-event handlers (`onFileIsStarted`, `onFileIsContinued`, `onFileIsUploaded`) must `return true`; `null` fails the file (`BXU350.1`). Handlers are global per request (`AddEventHandler('main_bxu', …)`). Image width/height limits are browser-only: check on the server.
- JS core `uploader` (`BX.Uploader`) is CJSCore-registered: `Extension::load('uploader')` works, `import` does not.
