---
name: bitrix-cms-basics
description: 'Классическая CMS Битрикс: сайты, шаблоны, страницы, меню, PageNavigation и пользовательские поля CUserTypeEntity. Используй для структуры сайта, навигации и типов UF.'
---

# Основы CMS

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

| Задача | Читать |
| --- | --- |
| Сайты, шаблон сайта, `#WORK_AREA#` | `rules/sites-templates.md` |
| Размещение CSS, `Asset::addCss()`, объединение стилей | `rules/styles.md` |
| Свойства страницы и раздела, `.section.php`, меню, включаемые области, цепочка навигации, пользователи | `rules/pages.md` |
| Пагинация: `PageNavigation`, обратная и административная, `bitrix:main.pagenavigation` | `rules/page-navigation.md` |
| Пользовательские поля: схема, значения, публичный и административный вывод | `rules/user-fields.md` |
| Собственный тип UF и компонент его вывода | `rules/user-field-types.md` |
| Страницы Landing / Sites24 | `bitrix-landing` |
| Почтовые события и шаблоны | `bitrix-mail` |
| Файлы, загрузка | `bitrix-files` |

## Общие правила

- Шаблоны, компоненты и включаемые файлы — в `/local/`; шаблоны ядра переопределяй копией.
- Пагинация: считай строки с фильтром запроса, до `initFromUri()`; не включай `allowAllRecords(true)` для больших таблиц.
- Схема UF — только через `CUserTypeEntity`; `UserFieldTable` и `UserTable` доступны только для чтения.
- Экранируй вывод шаблона (`htmlspecialcharsbx`).
- Новый код: `Asset::addCss()` / `Extension::load()` вместо `SetAdditionalCSS()`; чтение через D7 (`SiteTable`, `UserTable`).
