# Размещение и структура

Компонент получает данные через API модулей / сервисы и выводит их. Для целого раздела (каталог, личный кабинет) предпочитай контроллер + маршруты (`bitrix-routing`); комплексный ЧПУ — когда разделом управляет визуальный редактор.

## Размещение

- Свой: `/local/components/<vendor>/<name>/`, вызов `vendor:name` (`vendor:catalog.list`). Не используй пространство `bitrix/` или другого поставщика; не меняй `/bitrix/components/`.
- Компонент ядра / партнёра с другой разметкой: переопредели шаблон (`rules/template.md`), а не компонент.
- В модуле: `install/components/<vendor>/<name>/`, копируется при установке (`bitrix-modules`).

Заготовка (`bitrix-console-commands`):

```bash
php bitrix/bitrix.php make:component vendor:catalog.list --no-module              # /local/components/vendor/catalog.list
php bitrix/bitrix.php make:component vendor:catalog.list --module=vendor.catalog  # /local/modules/vendor.catalog/install/components/…
```

Без `--no-module` компонент попадёт в `install/components/` модуля; по умолчанию модуль берётся из первого сегмента имени (`catalog`). `--local` влияет только на пространство `bitrix`.

## Каталог

```
/local/components/vendor/catalog.list/
├── class.php              # наследник CBitrixComponent
├── .description.php       # имя и место в дереве визуального редактора
├── .parameters.php        # форма параметров
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

## Вызов

```php
$APPLICATION->IncludeComponent('vendor:catalog.list', '', [
    'IBLOCK_ID' => 12,
    'COUNT' => 10,
    'CACHE_TYPE' => 'A',
    'CACHE_TIME' => 3600,
], $component ?? false);   // четвёртый аргумент в шаблоне другого компонента
```

В шаблоне комплексного компонента всегда передавай `$component`: вложенные шаблоны ищутся в шаблоне родителя, данные кеша присоединяются к родителю.

## `$arParams` / `$arResult`

- `onPrepareComponentParams()` получает исходные значения. Затем ядро сохраняет их в `~KEY`, а строки `KEY` экранирует для HTML, в том числе на один уровень массива. Для HTML/URL, которые экранируешь сам, читай `~KEY`.
- Начальный `$arResult` — `[]`. Оба массива переданы шаблону по ссылке: не переназначай и не удаляй их через `unset()`.

## `.description.php`

```php
<?php
use Bitrix\Main\Localization\Loc;

$arComponentDescription = [
    'NAME' => Loc::getMessage('VENDOR_CATALOG_LIST_NAME'),
    'DESCRIPTION' => Loc::getMessage('VENDOR_CATALOG_LIST_DESC'),
    'PATH' => ['ID' => 'vendor', 'NAME' => 'Разработчик', 'CHILD' => ['ID' => 'catalog', 'NAME' => 'Каталог']],
    'CACHE_PATH' => 'Y',
];
```

Без `PATH` компонент скрыт в визуальном редакторе. Верхние ID `content`, `service`, `communication`, `e-store`, `utility` — разделы ядра.

## `.parameters.php`

```php
<?php
use Bitrix\Main\Localization\Loc;

$arComponentParameters = [
    'GROUPS' => ['SETTINGS' => ['NAME' => Loc::getMessage('VENDOR_GROUP_SETTINGS'), 'SORT' => 100]],
    'PARAMETERS' => [
        'IBLOCK_ID' => ['PARENT' => 'SETTINGS', 'NAME' => Loc::getMessage('VENDOR_IBLOCK_ID'), 'TYPE' => 'STRING'],
        'SET_TITLE' => [],   // флажок ядра
        'CACHE_TIME' => [],  // блок кеширования ядра
    ],
];
```

`TYPE`: `STRING`, `TEXT`, `LIST` (`VALUES`, `MULTIPLE`, `ADDITIONAL_VALUES`, `REFRESH`), `CHECKBOX`, `FILE`, `COLORPICKER`, `CUSTOM` (JS-виджет). Подсказки: фразы `<PARAM>_TIP` в `lang/<lang>/.parameters.php`.

Связанные темы: компоненты UF (`BaseUfComponent`) — `bitrix-cms-basics`; заголовок, кнопки и фильтр в стиле Битрикс24 — `Bitrix\UI\Toolbar\Facade\Toolbar` (`bitrix-ui`), по умолчанию в `bitrix:ui.sidepanel.wrapper` (`HIDE_TOOLBAR` / `PLAIN_VIEW` скрывают панель).
