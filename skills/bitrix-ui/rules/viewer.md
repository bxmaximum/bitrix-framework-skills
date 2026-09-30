# File viewer (`ui.viewer`)

Opens images, video, audio, PDF and code over the page, with gallery, download and print. `Extension::load('ui.viewer')`; a delegated click handler opens any `[data-viewer]` element (always in the top window, slider-aware). Markup comes from PHP `Bitrix\Main\UI\Viewer\ItemAttributes`.

```php
use Bitrix\Main\UI\Extension;
use Bitrix\Main\UI\Viewer\ItemAttributes;

Extension::load('ui.viewer');

$attributes = ItemAttributes::tryBuildByFileId($fileId, '/local/orders/file.php?id=' . $fileId) // unknown type on bad id
    ->setTitle($originalName)            // escaped
    ->setGroupBy('order-' . $orderId);   // same group = one gallery
?>
<a href="#" <?= $attributes ?>><?= htmlspecialcharsbx($originalName) ?></a>
```

- Builders: `buildByFileId()` / `buildByFileData($fileArray, $uri)` throw `ArgumentException`; `tryBuild*()` fall back to type `unknown`; `buildAsUnknownType($uri)`.
- Setters: `setTitle()`, `setGroupBy()`, `setViewerType()`, `setTypeClass()`, `setExtension()`, `addAction(['type' => 'download'])`, `setAttribute('data-viewer-preview', $thumbUrl)`. `setSources()`, `setWidth()`, `setHeight()`, `toVueBind()` are **Since main 26.250\*** (older: one `data-src` source, markup via `toString()`). `toDataSet()` for JS-built markup.
- Types by `CONTENT_TYPE`: image, video, audio, document (PDF), code (text, ≤ 2 MB), unknown. Office documents and non-web video are converted by the `transformer` module; without it they cannot be previewed (download only).

## File endpoint

The viewer sends service headers (`BX-Viewer`, `BX-Viewer-src`, …) and preview requests to the source URI. Answer with `BFile::createByFileId($fileId)` from a controller action, or `CFile::ViewByUser()` in a plain script: both hand those requests to `PreviewManager`. A hand-made `readfile()` breaks previews. The viewer checks no rights: the endpoint must (`bitrix-files`).

- Warm up conversion early: `(new \Bitrix\Main\UI\Viewer\PreviewManager())->generatePreview($fileId)` (`null` without `transformer`).

## JS

- `BX.UI.Viewer.Instance` (singleton, top-window aware): `openByNode(node)`, `setItems(items)` → Promise, `open(index)`, `close()`, `isOpen()`, `getCurrentItem()`.
- Markup added later inside a container: `BX.UI.Viewer.bind(container)`; item from a node: `BX.UI.Viewer.buildItemByNode(node)`.
- Events are legacy: `BX.addCustomEvent('BX.UI.Viewer.Controller:onAfterShow', (controller, item) => …)`; also `onBeforeShow`, `onSetItems`, `onClose`, `onItemError`.
- Inline PDF: `new BX.UI.Viewer.SingleDocumentController({ baseContainer: node, stretch: true })`, then `setItems([...])` and `open()`.

## Custom file type

Server: subclass `Bitrix\Main\UI\Viewer\Renderer\Renderer` (`static getAllowedContentTypes()`, `static getJsType()`, `render()`), register it from a `main:onPreviewRendererBuildList` handler returning `new EventResult(EventResult::SUCCESS, [MyRenderer::class])` (`bitrix-events`). Client: `BX.UI.Viewer.addType(type, 'BX.Vendor.ViewerItem')` with a class extending `BX.UI.Viewer.Item`.
