# Просмотрщик файлов (`ui.viewer`)

Открывает поверх страницы изображения, видео, аудио, PDF, код; галерея, скачивание, печать. `Extension::load('ui.viewer')`: делегированный обработчик открывает [data-viewer] в верхнем окне с учётом слайдеров. Атрибуты формирует PHP `Bitrix\Main\UI\Viewer\ItemAttributes`.

```php
use Bitrix\Main\UI\Extension;
use Bitrix\Main\UI\Viewer\ItemAttributes;

Extension::load('ui.viewer');

$attributes = ItemAttributes::tryBuildByFileId($fileId, '/local/orders/file.php?id=' . $fileId) // плохой ID: тип unknown
    ->setTitle($originalName) // экранируется
    ->setGroupBy('order-' . $orderId); // одна группа — одна галерея
?>
<a href="#" <?= $attributes ?>><?= htmlspecialcharsbx($originalName) ?></a>
```

- buildByFileId() / buildByFileData($fileArray, $uri) бросают ArgumentException; tryBuild*() возвращают тип unknown; buildAsUnknownType($uri).
- Методы: `setTitle()`, `setGroupBy()`, `setViewerType()`, `setTypeClass()`, `setExtension()`, addAction(['type' => 'download']), setAttribute('data-viewer-preview', $thumbUrl). `setSources()`, `setWidth()`, `setHeight()`, toVueBind() — **Since main 26.250\***; раньше один data-src, разметка через toString(). Для JS-разметки — toDataSet().
- Типы по CONTENT_TYPE: image, video, audio, document (PDF), code (текст до 2 МБ), unknown. Office и не-веб-видео преобразует transformer; без него только скачивание.

## Обработчик выдачи

Просмотрщик отправляет заголовки BX-Viewer, BX-Viewer-src и запросы предпросмотра на URI файла. Отвечай `BFile::createByFileId($fileId)` из действия либо `CFile::ViewByUser()` из скрипта: оба передают в PreviewManager. Самописный readfile() ломает предпросмотр. Права проверяет обработчик выдачи, не просмотрщик (`bitrix-files`).

- Предварительное преобразование: `(new \Bitrix\Main\UI\Viewer\PreviewManager())->generatePreview($fileId)`, null без transformer.

## JS

- BX.UI.Viewer.Instance — единый экземпляр с учётом верхнего окна: openByNode(node), setItems(items) → Promise, open(index), close(), `isOpen()`, `getCurrentItem()`.
- Поздняя разметка: BX.UI.Viewer.bind(container); запись из узла: BX.UI.Viewer.buildItemByNode(node).
- События старого формата: `BX.addCustomEvent('BX.UI.Viewer.Controller:onAfterShow', (controller, item) => …)`; также onBeforeShow, onSetItems, onClose, onItemError.
- PDF внутри страницы: `new BX.UI.Viewer.SingleDocumentController({ baseContainer: node, stretch: true })`, затем setItems([...]), open().

## Свой тип файла

Сервер: наследуй `Bitrix\Main\UI\Viewer\Renderer\Renderer` (static `getAllowedContentTypes()`, static `getJsType()`, `render()`); зарегистрируй обработчик main:onPreviewRendererBuildList, возвращающий `new EventResult(EventResult::SUCCESS, [MyRenderer::class])` (`bitrix-events`). Клиент: `BX.UI.Viewer.addType(type, 'BX.Vendor.ViewerItem')`, класс-наследник BX.UI.Viewer.Item.
