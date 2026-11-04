# Приём загрузок

## Своё действие (multipart-форма)

Файлы не привязываются к параметрам действия: читай из запроса, передавай сервису (`bitrix-controllers`). Фильтры по умолчанию требуют CSRF, отправляй `sessid`.

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
        return $result->addError(new Error('Файл не загружен', 'NO_FILE'));
    }

    $file['type'] = MimeType::getByFilePath($file['tmp_name']); // сигнатура содержимого, не заголовок клиента
    $error = \CFile::CheckFile($file, 10 * 1024 * 1024, ['application/pdf'], 'pdf');
    if ($error !== '')
    {
        return $result->addError(new Error($error, 'BAD_FILE'));
    }

    $file['MODULE_ID'] = 'vendor.docs';
    $fileId = (int)\CFile::SaveFile($file, 'vendor.docs');
    if ($fileId <= 0)
    {
        return $result->addError(new Error('Не удалось сохранить файл', 'SAVE_FAILED'));
    }
    // привяжи $fileId к $docId в своей таблице
    return $result->setData(['fileId' => $fileId]);
}
```

- `is_uploaded_file()` блокирует поддельный массив с `tmp_name = /etc/passwd`: `SaveFile()` копирует любой читаемый путь.
- `CFile::CheckFile($arFile, $intMaxSize = 0, $mimeType = false, $strExt = false, $bForceMD5 = false, $bSkipExt = false)` возвращает `''` при успехе **и пустом `name`**: сначала проверь `error`. `$mimeType` — список префиксов для `$arFile['type']`; `$strExt` — список расширений через запятую.
- Изображения: `CFile::CheckImageFile($arFile, $iMaxSize = 0, $iMaxWidth = 0, $iMaxHeight = 0)` декодирует изображение. Успех — `null`, иначе строка ошибки; проверяй `(string)$error !== ''`.
- Содержимое: `MimeType::getByFilePath()` или `getByContent()` (finfo). Проверка изображения: `(new \Bitrix\Main\File\Image($path))->getInfo()` → `Image\Info` либо `null`.
- Всегда разрешай конкретные расширения. Файлы `html`, `htm`, `svg`, `xml`, `js` в `/upload/` при открытии выполняются в origin сайта: сохранённый XSS.
- `name="files[]"` даёт поключевой формат PHP (`name[0]`, `tmp_name[0]`, …). Большие файлы загружай частями через `ui.uploader` (`rules/ui-uploader.md`), а не повышением лимитов PHP.

## Старый `bitrix:main.file.input` для совместимости

Сразу сохраняет каждый файл в b_file. При отправке формы оставляй ID, зарегистрированные для контрола: `\Bitrix\Main\UI\FileInputUtility::instance()->checkFiles($controlId, $ids)`, где `$controlId` — `CONTROL_ID` (по умолчанию `'mfi' . INPUT_NAME`). Иначе можно привязать произвольный ID b_file (IDOR).
