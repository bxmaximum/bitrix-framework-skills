# Поля загрузки файлов

Новый код: ui.uploader со своим UploaderController. Старые main-загрузчики — для существующих форм. Серверная часть, права, закрепление временных файлов и проверка содержимого: `bitrix-files`.

## `ui.uploader`

| Расширение | Экспорт, глобальный BX.UI.Uploader |
| --- | --- |
| ui.uploader.tile-widget | TileWidget: плитки с предпросмотром |
| ui.uploader.stack-widget | StackWidget, StackWidgetSize (LARGE, MEDIUM, SMALL, TINY): компактный |
| ui.uploader.core | Uploader, UploaderEvent, FileStatus, FileEvent: своя разметка |
| ui.uploader.vue | Интеграция Vue, база VueUploaderWidget |

```js
import { TileWidget } from 'ui.uploader.tile-widget';

const widget = new TileWidget({
    controller: 'vendor:orders.fileUploader.docController', // → \Vendor\Orders\FileUploader\DocController
    controllerOptions: { orderId: 42 }, // данные клиента: проверяй в контроллере
    files: savedFileIds, // числа; загрузка через canView() + verifyFileOwner()
    hiddenFieldsContainer: form,
    hiddenFieldName: 'DOCS', // DOCS[] при multiple: новые токены и существующие ID
    multiple: true,
    maxFileSize: 10 * 1024 * 1024, // только интерфейс; сервер проверяет getConfiguration()
    acceptedFileTypes: ['.pdf', '.docx'],
    events: { [UploaderEvent.FILE_UPLOAD_COMPLETE]: (event) => {} }, // импорт UploaderEvent из ui.uploader.core
});
widget.renderTo(node);
const uploader = widget.getUploader(); // базовый Uploader: getFiles(), removeFiles(), destroy()
```

- `new StackWidget(uploaderOptions, { size: StackWidgetSize.MEDIUM })`; TileWidget также принимает настройки виджета вторым аргументом.
- `vendor:module.path.className` → `\Vendor\Module\Path\ClassName`. В .settings.php модуля нужно разрешение: `'ui.uploader' => ['value' => ['allowUseControllers' => true], 'readonly' => true]`.
- События UploaderEvent.*: File:onAdd, File:onRemove, File:onUploadComplete, File:onError, onUploadComplete, onError, onMaxFileCountExceeded.
- Скрытый input нового файла появляется после загрузки: блокируй отправку формы до её завершения.
- Скрытые поля содержат новые токены и ID b_file сохраняемых старых файлов. При сохранении закрепляй токены, проверяй ID (`bitrix-files`).
- Параллельная загрузка и загрузка по подписанным URL (ParallelUploadController, PresignedUploadController) — **Since ui 26.600**, по умолчанию выключены (`bitrix-settings`).

## Старые загрузчики main, совместимость

- bitrix:main.file.input: INPUT_NAME (обязателен, [a-zA-Z0-9_]), INPUT_VALUE (ID файлов), MULTIPLE, ALLOW_UPLOAD (A|I|F|N), ALLOW_UPLOAD_EXT, MAX_FILE_SIZE, MODULE_ID, CONTROL_ID (по умолчанию mfi + INPUT_NAME). При отправке фильтруй ID через `FileInputUtility::instance()`->checkFiles($controlId, $ids) (`bitrix-files`).
- Административное поле FileInput возвращает HTML через `show()`: нужен echo. Подпись загрузки, ограничения и безопасное сохранение временных файлов — `bitrix-files`, rules/file-input.md.
- Свой приёмник `Bitrix\Main\UI\Uploader\Uploader` (`checkPost()`): onFileIsStarted, onFileIsContinued, onFileIsUploaded обязаны вернуть true; null даёт ошибку BXU350.1. Обработчики глобальны на запрос (`AddEventHandler('main_bxu', …)`). Лимиты ширины / высоты действуют только в браузере: проверяй сервером.
- JS core uploader (BX.Uploader) зарегистрирован через CJSCore: `Extension::load('uploader')` работает, import — нет.

**Since ui 26.700** браузерный ImageSizeFilter проверяет реальный формат изображения, а не только имя / MIME клиента. Переименованный webp при запрете webp отклоняется, либо при ignoreUnknownImageTypes идёт как обычный файл. Это не серверная защита: проверка содержимого в UploaderController остаётся обязательной. На старом ui не рассчитывай на такую проверку в браузере.
