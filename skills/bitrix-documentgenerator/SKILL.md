---
name: bitrix-documentgenerator
description: 'Генератор документов Битрикс24: DOCX/PDF из шаблонов и своих данных, Document::createByTemplate, свой DataProvider. Используй для полей шаблона, нумерации, хранения и прав.'
---

# Генератор документов Битрикс

Baseline: main 23.0+ · Verified: documentgenerator 26.400.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

Только Битрикс24, облако и коробка: установка требует `humanresources`, в «Управлении сайтом» модуля нет. Для PDF/JPG нужны `transformer` и сервер документов.

| Задача | Читать |
| --- | --- |
| Поля подстановки, модификаторы, таблицы, изображения, создание шаблона кодом | `rules/templates.md` |
| Данные своей сущности: класс поставщика, поля, списки, регистрация | `rules/providers.md` |
| Генерация DOCX/PDF, номера, хранение, удаление, события | `rules/generation.md` |
| Права модуля, роли, доступ к шаблону, REST, компоненты интерфейса | `rules/access-rest.md` |

Документы CRM (счета, сделки) используют поставщиков CRM: `bitrix24-crm`.

## Общие правила

- `Loader::requireModule('documentgenerator')`; код — в своём модуле `/local/modules/`, поставщик зарегистрирован под ID этого модуля.
- `MODULE_ID` шаблона = `MODULE` поставщика = установленный модуль; иначе поставщик молча игнорируется.
- Вызывай `Template::setSourceType()` до `getFields()` / `Document::createByTemplate()`, проверяй `getSourceType()`.
- Проверяй права явно: `UserPermissions`, доступ к шаблону, `Document::hasAccess()`. Загрузка и создание прав не проверяют; пользователь `0` проходит проверки данных.
- Ссылки на файлы — `b_documentgenerator_file.ID` (`FILE_ID`, `PDF_ID`); преобразование — `FileTable::getBFileId()`.
- PDF создаётся асинхронно: `getFile(false)` либо пропуск ошибок преобразования; обрабатывай `onDocumentTransformationComplete`.
