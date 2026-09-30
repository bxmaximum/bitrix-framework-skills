# Templates and epilog

## Template search

Order from `CBitrixComponentTemplate::generatePossibleTemplatePath()` (`main/classes/general/component_template.php`). `/local/` is always checked before `/bitrix/` (`BX_PERSONAL_ROOT`); `<parent…>` steps apply only when the component was included with a parent (4th `IncludeComponent()` argument).

1. `/local/templates/<site_template>/components/<parent_path>/<parent_tpl>/<component_path>/`
2. `/local/templates/.default/components/<parent_path>/<parent_tpl>/<component_path>/`
3. `/local/components/<parent_path>/templates/<parent_tpl>/<component_path>/`
4. `/local/templates/<site_template>/components/<component_path>/`
5. `/local/templates/.default/components/<component_path>/`
6. `/local/components/<component_path>/templates/`
7–12. The same six under `/bitrix/`.

Override a kernel template: copy `/bitrix/components/bitrix/<name>/templates/.default/` to `/local/templates/<site_template>/components/bitrix/<name>/<tpl>/` (or `.default` site template for all sites). Updates never touch the copy — re-check it after kernel updates.

## `template.php`

```php
<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); } ?>
<div class="catalog-list">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <a href="<?= htmlspecialcharsbx($item['URL']) ?>"><?= htmlspecialcharsbx($item['NAME']) ?></a>
    <?php endforeach; ?>
</div>
```

Variables: `$arResult`, `$arParams`, `$templateName`, `$templateFile`, `$templateFolder`, `$componentPath`, `$component`, `$this` (`CBitrixComponentTemplate`), `$templateData`, `$APPLICATION`, `$USER`. `style.css` / `script.js` next to the template load automatically.

## `result_modifier.php`

Runs before `template.php` to reshape `$arResult` without touching the class.

- On a cache hit it does not run (neither does the template).
- Changes to `$arResult` reach the component; `$arParams` is a copy — changes affect the template only.
- Don't set title/meta here when caching is on — use the epilog.

## `component_epilog.php`

Runs after the template on every hit, cached or not: title, meta, breadcrumbs, counters.

- On a cache hit it gets only the `$arResult` keys listed in `setResultCacheKeys()` plus `$templateData` from the template — rely on nothing else:

```php
$this->setResultCacheKeys(['NAME', 'SECTION_NAME']);   // class.php
$templateData = ['ITEM_COUNT' => count($arResult['ITEMS'])]; // template.php
```

- Phrases are not auto-loaded: `lang/<lang>/component_epilog.php` + `\Bitrix\Main\Localization\Loc::loadMessages(__FILE__)` in the epilog.
- Code in `class.php` after `includeComponentTemplate()` runs after the epilog and overrides its changes (e.g. `SetTitle`).
