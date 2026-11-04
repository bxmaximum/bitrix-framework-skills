# Хранение b_file через CFile

Строка `b_file`: `ID`, `MODULE_ID`, `SUBDIR`, `FILE_NAME`, `ORIGINAL_NAME`, `CONTENT_TYPE`, `FILE_SIZE`, `WIDTH`, `HEIGHT`, `DESCRIPTION`, `EXTERNAL_ID`, `HANDLER_ID` (облачный контейнер). Локальный путь: `/<опция main:upload_dir>/<SUBDIR>/<FILE_NAME>`. API записи D7 нет; `Bitrix\Main\FileTable` — для чтения, связей и отчётов.

## Сохранение

```php
$file = \CFile::MakeFileArray($absPath);             // доверенный локальный путь
$file['MODULE_ID'] = 'vendor.docs';
$file['description'] = 'Скан договора';
$fileId = (int)\CFile::SaveFile($file, 'vendor.docs'); // /upload/vendor.docs/<3 hex>/<random>.<ext>
if ($fileId <= 0)
{
    // неверное имя, опасный файл, квота или ошибка записи
}
```

- Сигнатура: `SaveFile($arFile, $strSavePath, $forceRandom = false, $skipExtension = false, $dirAdd = '')` → ID либо `false`.
- Ключи `$arFile`: `name` и `tmp_name` (копирование) либо `content` (байты); также `type`, `MODULE_ID`, `description`, `external_id`, `old_file` (ID удаляемого после успешного сохранения файла).
- Физическое имя по умолчанию: 32 случайных символа с исходным расширением. `ORIGINAL_NAME` хранит имя клиента с заменой недопустимых символов: экранируй при выводе.
- `save_original_file_name = Y` сохраняет исходное имя в случайном подкаталоге. `translit_original_file_name = Y` транслитерирует его. **Since main 26.800** транслитерируется только физическое имя (безопасные символы `._-()`), но не `ORIGINAL_NAME`. Раньше менялись оба.
- `SaveFile()` убирает расширения скриптов (`php`, `phtml`, … из `fileman:~script_files`), запрещает имена вроде `.htaccess`. `html`, `svg`, `js` остаются: перед сохранением разрешай конкретные расширения (`rules/uploads.md`).
- Тип и размер не проверяются. Нормализованный `type` сохраняется как передан; изображение без читаемых размеров записывается как `application/octet-stream`.
- Одинаковое содержимое дедуплицируется (`b_file_hash`): несколько ID могут ссылаться на физический файл. `Delete()` сохраняет его до удаления последнего ID.
- Копия: `CFile::CloneFile(int $fileId): ?int` создаёт новый ID того же файла; `CFile::CopyFile($FILE_ID)` копирует и физический файл.

## MakeFileArray

`CFile::MakeFileArray($path, $mimetype = false, $skipInternal = false, $external_id = '')` возвращает массив формата `$_FILES`. Принимает:

- **ID b_file.** `tmp_name` — сам файл либо временная копия из облака. Читай, не перемещай и не удаляй.
- **Локальный путь**, абсолютный либо относительно `DOCUMENT_ROOT`. Доступен любой читаемый файл, включая `/bitrix/.settings.php`. MIME берётся через `mime_content_type()`.
- **URL `http(s)://`.** Загрузка через `HttpClient`, приватные IP запрещены.
- **URL `ftp(s)://`.** Приватные IP запрещены только **Since main 26.800**; на старом ядре извне принимай только `https?://`.

Возвращает `null` для отсутствующего файла, каталога, `php://`/`phar://` (кроме `php://input`). Пользовательские пути и URL создают чтение произвольных файлов и SSRF. URL пользователя загружай с проверкой разрешённых адресов (`bitrix-http-client`), затем передавай временный файл.

## Чтение

```php
$file = \CFile::GetFileArray($fileId);  // строка + SRC (URL облачного файла) либо false
$url = \CFile::GetPath($fileId);        // SRC либо null
$thumb = \CFile::ResizeImageGet($fileId, ['width' => 300, 'height' => 300], BX_RESIZE_IMAGE_PROPORTIONAL, true);
// ['src' => …, 'width' => …, 'height' => …, 'size' => …] либо false
```

- `ResizeImageGet($file, $arSize, $resizeType = BX_RESIZE_IMAGE_PROPORTIONAL, $bInitSizes = false, $arFilters = false, $bImmediate = false, $jpgQuality = false)`.
- Режимы: `BX_RESIZE_IMAGE_PROPORTIONAL` (вписать), `BX_RESIZE_IMAGE_PROPORTIONAL_ALT` (ширина рамки для длинной стороны), `BX_RESIZE_IMAGE_EXACT` (обрезка по центру).
- Задавай `width` и `height`: нулевая сторона отключает уменьшение. Увеличения нет: размер ≥ исходного возвращает оригинал. Фильтр по умолчанию — `sharpen`.
- Первый вызов синхронно пишет `/upload/resize_cache/<SUBDIR>/<w>_<h>_<type>/<FILE_NAME>`: выполняй внутри кешируемого вывода. `CFile::Delete()` очищает копии.
- `CFile::GetByID()`/`GetFileArray()` учитывают замены содержимого ниже; `FileTable` возвращает исходную строку.

## Удаление

```php
\CFile::Delete($fileId); // ОПАСНО: удаляет файл, кеш уменьшенных копий и версии; ничего не возвращает
```

Удаляй файлы вместе с сущностью-владельцем в сервисе либо ORM `OnAfterDelete`. Иначе они останутся навсегда.

## Замена содержимого с сохранением ID — Since main 26.800

```php
use Bitrix\Main\File\ContentReplacer;

$newId = (int)\CFile::SaveFile($newFileArray, 'vendor.docs'); // должен быть > $fileId
$result = (new ContentReplacer())->replace($fileId, $newId, $currentReplacementId, 'vendor.docs');
// $currentReplacementId: сначала null, затем предыдущий $newId (оптимистическая блокировка)
```

- После замены `CFile::GetByID($fileId)` и `GetFileArray()` возвращают новую версию из `b_file_version`; предыдущая замена удаляется. Данные результата: `['changed' => bool]`.
- Ошибки: `FILE_REPLACEMENT_INVALID`, `_CONFLICT`, `_LOCKED`, `_WRITE_FAILED`. Вызывай вне транзакции БД. `$owner` (`[a-zA-Z0-9_.-]{1,100}`) — метка сервиса, а не проверка доступа.
- На старом ядре сохрани новый файл и обнови ID в сущности.

## События и облака

- События `main`: `OnFileSave` (`true` — сохранено внешним хранилищем), `OnAfterFileSave`, `OnMakeFileArray`, `OnGetFileSRC`, `OnBeforeResizeImage`, `OnAfterResizeImage`, `OnFileDelete`, `OnPhysicalFileDelete`.
- Модуль `clouds` использует эти события: файлы по правилам контейнера идут в S3-совместимое хранилище (`HANDLER_ID`), `SRC` становится облачным URL, `MakeFileArray($id)` скачивает временную копию. Работа через API сохраняется; склейка `/upload/...` ломается.
