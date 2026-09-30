# Sites and site templates

## Sites (multisite)

ORM: `\Bitrix\Main\SiteTable` (table `b_lang`); legacy `CSite`. Fields: `LID` (primary, `s1`), `NAME`, `DIR`, `DOC_ROOT`, `SERVER_NAME`, `SITE_NAME`, `LANGUAGE_ID`, `CULTURE_ID`, `ACTIVE`, `DEF`.

```php
$site = \Bitrix\Main\SiteTable::getRow([
    'filter' => ['=LID' => SITE_ID],
    'select' => ['LID', 'DIR', 'SERVER_NAME', 'LANGUAGE_ID'],
]);
$docRoot = \Bitrix\Main\SiteTable::getDocumentRoot(SITE_ID);
$byHost = \Bitrix\Main\SiteTable::getByDomain($host, $directory);
```

`SITE_ID`, `SITE_DIR`, `SITE_TEMPLATE_PATH` exist after kernel init; site-specific code checks them instead of hard-coded paths.

## Site template

```
/local/templates/<template_id>/
├── header.php, footer.php   # page frame around #WORK_AREA#
├── description.php          # $arTemplate meta, EDITOR_STYLES
├── styles.css               # content styles, also loaded by the visual editor
├── template_styles.css      # frame styles
├── .styles.php              # visual editor style list
├── components/<ns>/<name>/<tpl>/   # component template overrides (`bitrix-components`)
├── page_templates/          # page layouts offered by the visual editor
└── lang/
```

- The template must contain `#WORK_AREA#` (single-file template or the `header.php`/`footer.php` split); the kernel puts the page body there. Missing → admin error "set the #WORK_AREA# separator".
- Keep templates in `/local/templates/`, never edit `/bitrix/templates/`. `.default` holds shared parts for all templates.
- Template per site (and per condition: folder, URL param, PHP expression) in Admin → Sites → Edit.
- CSS placement: `rules/styles.md`.
