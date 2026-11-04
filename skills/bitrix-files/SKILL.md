---
name: bitrix-files
description: 'Файлы Битрикс: b_file через CFile (SaveFile, MakeFileArray, ResizeImageGet), проверка загрузок и защищённая выдача. Используй также для серверной части ui.uploader.'
---

# Файлы Битрикс

Baseline: main 23.0+ · Verified: main 26.1000.0, ui 26.700.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

| Задача | Читать |
| --- | --- |
| Сохранение, чтение, уменьшение, удаление; `b_file`, облака, замена содержимого с сохранением ID | `rules/storage.md` |
| Загрузка в своё действие: проверки, MIME, старый `main.file.input` | `rules/uploads.md` |
| Административное поле FileInput, временная загрузка и сохранение формы | `rules/file-input.md` |
| Серверная часть `ui.uploader`: `UploaderController`, закрепление временных файлов | `rules/ui-uploader.md` |
| Выдача: `BFile`, приватные файлы, просмотрщик | `rules/delivery.md` |

JS-виджеты `ui.uploader`, `ui.viewer`: `bitrix-ui`. Настройки загрузчика в `.settings.php`: `bitrix-settings`. Файловые поля: `bitrix-iblocks`, `bitrix-highloadblock`.

## Общие правила

- Записывай `b_file` только через `CFile`. `FileTable` — только чтение: `add`/`update`/`delete` бросают `NotImplementedException`. Храни ID файла, а не путь.
- URL: `CFile::GetFileArray()['SRC']` или `CFile::GetPath()`; локальное содержимое: `CFile::MakeFileArray($id)['tmp_name']`. Не склеивай `/upload/` + `SUBDIR`: облака, опция `upload_dir` и замены ломают такой путь.
- Имя, MIME и размер клиента недостоверны: разрешай конкретные расширения, определяй MIME по содержимому, декодируй изображения.
- Не передавай пользовательский ввод в `CFile::MakeFileArray()`: метод читает локальные пути, ID b_file и URL.
- Всё внутри `/upload/` доступно по URL. Приватные файлы выдавай действием с проверкой прав.
- `CFile::Delete()` удаляет физический файл и кеш уменьшенных копий без возможности отмены.
