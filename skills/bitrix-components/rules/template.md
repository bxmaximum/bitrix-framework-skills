# Шаблоны и эпилог

## Поиск шаблона

Порядок из `CBitrixComponentTemplate::generatePossibleTemplatePath()` (`main/classes/general/component_template.php`). `/local/` проверяется до `/bitrix/` (`BX_PERSONAL_ROOT`); пути `<parent…>` — только при переданном родителе (четвёртый аргумент `IncludeComponent()`).

1. `/local/templates/<site_template>/components/<parent_path>/<parent_tpl>/<component_path>/`
2. `/local/templates/.default/components/<parent_path>/<parent_tpl>/<component_path>/`
3. `/local/components/<parent_path>/templates/<parent_tpl>/<component_path>/`
4. `/local/templates/<site_template>/components/<component_path>/`
5. `/local/templates/.default/components/<component_path>/`
6. `/local/components/<component_path>/templates/`
7–12. Те же шесть путей внутри `/bitrix/`.

Переопределение: скопируй `/bitrix/components/bitrix/<name>/templates/.default/` в `/local/templates/<site_template>/components/bitrix/<name>/<tpl>/` (либо шаблон сайта `.default` для всех сайтов). Обновления не меняют копию: перепроверяй после обновления ядра.

## `template.php`

```php
<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); } ?>
<div class="catalog-list">
    <?php foreach ($arResult['ITEMS'] as $item): ?>
        <a href="<?= htmlspecialcharsbx($item['URL']) ?>"><?= htmlspecialcharsbx($item['NAME']) ?></a>
    <?php endforeach; ?>
</div>
```

Переменные: `$arResult`, `$arParams`, `$templateName`, `$templateFile`, `$templateFolder`, `$componentPath`, `$component`, `$this` (`CBitrixComponentTemplate`), `$templateData`, `$APPLICATION`, `$USER`. `style.css` / `script.js` рядом с шаблоном загружаются автоматически.

## `result_modifier.php`

Выполняется до `template.php`, меняет `$arResult` без правки класса.

- При попадании в кеш не выполняется, как и шаблон.
- Изменения `$arResult` доходят до компонента; `$arParams` — копия, изменения действуют только в шаблоне.
- При включённом кеше задавай заголовок и метаданные в эпилоге.

## `component_epilog.php`

Выполняется после шаблона на каждом хите, включая кеш: заголовок, метаданные, цепочка навигации, счётчики.

- Из кеша получает только ключи `$arResult`, указанные в `setResultCacheKeys()`, и `$templateData` шаблона; на другое не рассчитывай:

```php
$this->setResultCacheKeys(['NAME', 'SECTION_NAME']);   // class.php
$templateData = ['ITEM_COUNT' => count($arResult['ITEMS'])]; // template.php
```

- Фразы не загружаются автоматически: добавь `lang/<lang>/component_epilog.php` и `\Bitrix\Main\Localization\Loc::loadMessages(__FILE__)` в эпилог.
- Код `class.php` после `includeComponentTemplate()` выполняется после эпилога и перекрывает его изменения (например, `SetTitle`).
