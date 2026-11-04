# Сайты и шаблоны

## Многосайтовость

ORM: `\Bitrix\Main\SiteTable` (таблица `b_lang`); старый API: `CSite`. Поля: `LID` (первичный ключ, `s1`), `NAME`, `DIR`, `DOC_ROOT`, `SERVER_NAME`, `SITE_NAME`, `LANGUAGE_ID`, `CULTURE_ID`, `ACTIVE`, `DEF`.

```php
$site = \Bitrix\Main\SiteTable::getRow([
    'filter' => ['=LID' => SITE_ID],
    'select' => ['LID', 'DIR', 'SERVER_NAME', 'LANGUAGE_ID'],
]);
$docRoot = \Bitrix\Main\SiteTable::getDocumentRoot(SITE_ID);
$byHost = \Bitrix\Main\SiteTable::getByDomain($host, $directory);
```

`SITE_ID`, `SITE_DIR`, `SITE_TEMPLATE_PATH` доступны после инициализации ядра; используй их вместо фиксированных путей сайта.

## Шаблон сайта

```
/local/templates/<template_id>/
├── header.php, footer.php   # обрамление страницы вокруг #WORK_AREA#
├── description.php          # метаданные $arTemplate, EDITOR_STYLES
├── styles.css               # стили содержимого; загружаются и в визуальный редактор
├── template_styles.css      # стили обрамления
├── .styles.php              # список стилей визуального редактора
├── components/<ns>/<name>/<tpl>/   # переопределения шаблонов компонентов (bitrix-components)
├── page_templates/          # макеты страниц для визуального редактора
└── lang/
```

- Нужен `#WORK_AREA#` в едином файле или между `header.php`/`footer.php`: сюда ядро помещает тело страницы. Без разделителя административный интерфейс выдаст ошибку его установки.
- Шаблоны храни в `/local/templates/`, не меняй `/bitrix/templates/`. `.default` содержит общие части всех шаблонов.
- Привязка шаблона к сайту и условию (папка, параметр URL, PHP-выражение): административный интерфейс → Сайты → Изменить.
- Размещение CSS: `rules/styles.md`.
