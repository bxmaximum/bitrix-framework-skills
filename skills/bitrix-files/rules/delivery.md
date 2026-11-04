# Выдача файлов

## Публичные и приватные файлы

- Веб-сервер отдаёт `SRC` из `/upload/` без проверок Битрикс. Случайное имя скрывает адрес, но не защищает файл.
- У приватных файлов не выводи `SRC`: возвращай `BFile` действием с проверкой прав. Прямой доступ к `/upload/<your savePath>/` закрой в nginx/Apache; ссылки `ResizeImageGet()` из этого каталога перестанут работать.

```php
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Engine\Response\BFile;

public function downloadAction(int $docId, DocFileService $files): ?BFile
{
    $fileId = $files->findReadableFileId($docId, (int)CurrentUser::get()->getId());
    if ($fileId === null)
    {
        $this->addError(new \Bitrix\Main\Error('Доступ запрещён', 'ACCESS_DENIED'));
        return null;
    }

    return BFile::createByFileId($fileId); // неизвестный ID: ObjectNotFoundException
}
```

- Для GET-ссылки сними CSRF-фильтр действия, сохрани `Authentication` и проверку прав (`bitrix-controllers`).
- `BFile::createByFileId($fileId, $name = null)`: `->showInline(true)` показывает внутри страницы только корректные изображения, остальное скачивается (`CFile::ViewByUser()`). `->setCacheTime($seconds)`.
- Миниатюры: `ResizedImage::createByImageId($imageId, $width, $height)`; по умолчанию `BX_RESIZE_IMAGE_EXACT`, изменение через `->setResizeType()`.
- `CFile::ViewByUser($arFile, ['force_download' => true, 'attachment_name' => $name, 'cache_time' => 0])` — старый механизм под `BFile`; используй только вне контроллеров.
- Большие файлы: с `main:bx_fast_download = Y` метод `ViewByUser()` отправляет `X-Accel-Redirect`; nginx должен отдавать путь внутренним перенаправлением.
- `ORIGINAL_NAME` приходит от клиента: в HTML оборачивай в `htmlspecialcharsbx()`. `BFile` убирает CR/LF из имени скачивания.

## Просмотрщик

- `ui.viewer` (`bitrix-ui`) открывает URL и отправляет запросы предпросмотра с заголовками `BX-Viewer*`. `ViewByUser()`/`BFile` отвечают на них; самописный `readfile()` ломает предпросмотр.
- Разметка: выведи `\Bitrix\Main\UI\Viewer\ItemAttributes::tryBuildByFileId($fileId, $downloadUrl)` в тег ссылки.
- Просмотрщик не проверяет права. Это обязанность действия по `$downloadUrl`.
