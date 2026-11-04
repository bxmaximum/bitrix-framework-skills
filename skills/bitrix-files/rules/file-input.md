# Административное поле FileInput

`Bitrix\Main\UI\FileInput` выводит административное поле, новые файлы принимает во временный каталог, но сам не привязывает их к сущности и не сохраняет в b_file. Для новых публичных форм используй ui.uploader (`rules/ui-uploader.md`); старый main.file.input сразу создаёт b_file (`rules/uploads.md`).

```php
use Bitrix\Main\UI\FileInput;

echo FileInput::createInstance([
    'name' => 'DOCUMENT_FILE[n#IND#]',
    'id' => 'document_file',
    'upload' => true,
    'allowUpload' => FileInput::UPLOAD_EXTENTION_LIST,
    'allowUploadExt' => 'pdf,docx',
    'maxCount' => 3,
    'maxSize' => 5 * 1024 * 1024,
])->show(['DOCUMENT_FILE[145]' => 145]);
```

- `show()` возвращает HTML, поэтому нужен echo; расширение подключает сам. id задавай уникальным на странице. Без upload: true новое не загружается.
- По умолчанию uploadType = path: новая запись формы — массив name/type/tmp_name/size/error, старая — ID b_file. hash возвращает ID временной загрузки, не ID b_file.
- Подпись FileInputReceiver включает тип приёма, allowUpload и allowUploadExt. **maxCount и maxSize не входят в подпись и проверяются только браузером**: проверяй число и фактический размер сервером, а MIME — по содержимому.
- `_del` и `_descr` вставляются перед первой скобкой: DOCUMENT_FILE[n0] → DOCUMENT_FILE_del[n0], DOCUMENT_FILE_descr[n0]. Удаление / сохранение применяет код формы после проверки прав сущности и принадлежности старых ID.
- tmp_name приходит из браузера. До `CFile::SaveFile` восстанови абсолютный путь относительно `CTempFile::GetAbsoluteRoot()`, нормализуй realpath(), проверь границу корня с завершающим разделителем, `is_file()`, доступ и содержимое. Не передавай произвольный путь клиента в MakeFileArray. `FileInput::prepareFile()` путь не проверяет и не преобразует.
- medialib, fileDialog, cloud по умолчанию выключены. Класс проверяет права источников: medialib_view_collection, fileman_view_file_structure, clouds_browse; облакам нужны активные контейнеры. Включай только нужные источники.
- thumbSize и вторые аргументы `createInstance()`/`show()` не влияют на результат. Для произвольных старых ядер сначала проверь класс и настройку в исходнике; эта схема также проверена на эталоне main 25.100.500.
