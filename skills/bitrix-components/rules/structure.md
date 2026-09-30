# Placement and structure

A component fetches data through module APIs/services and renders it. Whole sections (catalog, personal area): prefer controller + routes (`bitrix-routing`); complex SEF components when the visual editor must manage the section.

## Where

- Own: `/local/components/<vendor>/<name>/`, called as `vendor:name` (`vendor:catalog.list`). Never put yours into `bitrix/` or another vendor's namespace; never edit `/bitrix/components/`.
- Kernel/partner component, other markup: override the template (`rules/template.md`), not the component.
- Module-shipped: `install/components/<vendor>/<name>/`, copied on install (`bitrix-modules`).

Scaffold (`bitrix-console-commands`):

```bash
php bitrix/bitrix.php make:component vendor:catalog.list --no-module              # /local/components/vendor/catalog.list
php bitrix/bitrix.php make:component vendor:catalog.list --module=vendor.catalog  # /local/modules/vendor.catalog/install/components/…
```

Without `--no-module` the component goes into a module's `install/components/` — the module defaults to the first name segment (`catalog`). `--local` only matters for the `bitrix` namespace.

## Folder

```
/local/components/vendor/catalog.list/
├── class.php              # CBitrixComponent subclass
├── .description.php       # name, place in the visual editor tree
├── .parameters.php        # parameter form
├── lang/<lang>/class.php, .description.php, .parameters.php
└── templates/.default/
    ├── template.php, result_modifier.php, component_epilog.php
    ├── style.css, script.js
    └── lang/<lang>/template.php
```

## `class.php`

```php
<?php declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

final class VendorCatalogListComponent extends \CBitrixComponent
{
    public function onPrepareComponentParams($arParams): array
    {
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['COUNT'] = max(1, (int)($arParams['COUNT'] ?? 20));
        $arParams['CACHE_TIME'] = (int)($arParams['CACHE_TIME'] ?? 3600);

        return $arParams;
    }

    public function executeComponent(): void
    {
        if ($this->startResultCache(false, [$GLOBALS['USER']->GetUserGroupArray()]))
        {
            $this->arResult['ITEMS'] = \Bitrix\Main\DI\ServiceLocator::getInstance()
                ->get(\Vendor\Catalog\ItemReader::class)
                ->list($this->arParams['IBLOCK_ID'], $this->arParams['COUNT']);
            $this->setResultCacheKeys(['ITEMS']);
            $this->includeComponentTemplate();
        }
    }
}
```

## Call

```php
$APPLICATION->IncludeComponent('vendor:catalog.list', '', [
    'IBLOCK_ID' => 12,
    'COUNT' => 10,
    'CACHE_TYPE' => 'A',
    'CACHE_TIME' => 3600,
], $component ?? false);   // 4th arg inside another component's template
```

Inside a complex component's template always pass `$component`: nested templates are then searched in the parent template's folder and cached data is attached to the parent.

## `$arParams` / `$arResult`

- `onPrepareComponentParams()` gets raw values; afterwards the kernel stores each raw value under `~KEY` and HTML-escapes strings in `KEY` (one level into arrays). Read `~KEY` for HTML/URLs you escape yourself.
- `$arResult` starts as `[]`. Both are shared with the template by reference — never reassign or `unset()` them.

## `.description.php`

```php
<?php
use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME' => Loc::getMessage('VENDOR_CATALOG_LIST_NAME'),
    'DESCRIPTION' => Loc::getMessage('VENDOR_CATALOG_LIST_DESC'),
    'PATH' => ['ID' => 'vendor', 'NAME' => 'Vendor', 'CHILD' => ['ID' => 'catalog', 'NAME' => 'Catalog']],
    'CACHE_PATH' => 'Y',
];
```

Without `PATH` the component is hidden in the visual editor. Top-level IDs `content`, `service`, `communication`, `e-store`, `utility` are the kernel's sections.

## `.parameters.php`

```php
<?php
use Bitrix\Main\Localization\Loc;

$arComponentParameters = [
    'GROUPS' => ['SETTINGS' => ['NAME' => Loc::getMessage('VENDOR_GROUP_SETTINGS'), 'SORT' => 100]],
    'PARAMETERS' => [
        'IBLOCK_ID' => ['PARENT' => 'SETTINGS', 'NAME' => Loc::getMessage('VENDOR_IBLOCK_ID'), 'TYPE' => 'STRING'],
        'SET_TITLE' => [],   // kernel-provided checkbox
        'CACHE_TIME' => [],  // kernel-provided cache block
    ],
];
```

`TYPE`: `STRING`, `TEXT`, `LIST` (`VALUES`, `MULTIPLE`, `ADDITIONAL_VALUES`, `REFRESH`), `CHECKBOX`, `FILE`, `COLORPICKER`, `CUSTOM` (JS widget). Hints: `<PARAM>_TIP` phrases in `lang/<lang>/.parameters.php`.

Related: UF render components (`BaseUfComponent`) — `bitrix-cms-basics`; page title/buttons/filter in Bitrix24-style pages — `Bitrix\UI\Toolbar\Facade\Toolbar` (`bitrix-ui`), shown by default inside `bitrix:ui.sidepanel.wrapper` (`HIDE_TOOLBAR` / `PLAIN_VIEW` hide it).
