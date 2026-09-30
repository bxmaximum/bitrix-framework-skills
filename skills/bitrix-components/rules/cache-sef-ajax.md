# Cache, paged lists, SEF, AJAX

## Result cache

Cache ID = site, language, site template, component, template, every non-`~` param, time zone offset, plus `startResultCache()`'s 2nd arg. Add everything else that changes output:

```php
if ($this->startResultCache(false, [$GLOBALS['USER']->GetUserGroupArray()])) { … }
```

- `CACHE_TYPE = 'N'` disables caching; `'A'` follows the main option `component_cache_on`.
- On a hit neither `result_modifier.php` nor `template.php` runs; only keys from `setResultCacheKeys()` reach `component_epilog.php` (`rules/template.md`).
- Invalidate by tag: `bitrix-caching`.

## Paged list

Build `PageNavigation` before the cache and put the page into the cache key; otherwise every page shows cached page 1. Count before `initFromUri()` so bogus page numbers clamp instead of creating a cache entry each.

```php
public function executeComponent(): void
{
    $filter = ['=ACTIVE' => 'Y'];
    $nav = (new \Bitrix\Main\UI\PageNavigation('nav-news'))
        ->setPageSize($this->arParams['COUNT'])
        ->setRecordCount(NewsTable::getCount($filter, ['ttl' => 3600]));
    $nav->initFromUri();

    if ($this->startResultCache(false, [$nav->getCurrentPage(), $nav->getPageSize()]))
    {
        $this->arResult['ITEMS'] = $nav->getPageCount() > 0
            ? NewsTable::getList([
                'filter' => $filter,
                'offset' => $nav->getOffset(),
                'limit' => $nav->getLimit(),
            ])->fetchAll()
            : [];
        $this->arResult['NAV_OBJECT'] = $nav;
        $this->includeComponentTemplate();
    }
}
```

```php
// template.php
$APPLICATION->IncludeComponent('bitrix:main.pagenavigation', '', [
    'NAV_OBJECT' => $arResult['NAV_OBJECT'],
    'SEF_MODE' => 'N',
], $component);
```

Add `$nav->allRecordsShown()` to the key if `page-all` is allowed. `PageNavigation` details and custom pager markup: `bitrix-cms-basics` (`rules/page-navigation.md`).

## SEF (complex components)

`.parameters.php` declares pages: `'SEF_MODE' => ['list' => ['NAME' => …, 'DEFAULT' => '', 'VARIABLES' => []], 'detail' => ['NAME' => …, 'DEFAULT' => '#ELEMENT_ID#/', 'VARIABLES' => ['ELEMENT_ID']]]`; the call then carries `SEF_MODE = 'Y'`, `SEF_FOLDER`, `SEF_URL_TEMPLATES`.

```php
$templates = \CComponentEngine::makeComponentUrlTemplates(
    ['list' => '', 'detail' => '#ELEMENT_ID#/'],
    $this->arParams['SEF_URL_TEMPLATES'] ?? []
);
$variables = [];
$page = (new \CComponentEngine($this))
    ->guessComponentPath($this->arParams['SEF_FOLDER'], $templates, $variables) ?: 'list';

$this->arResult = ['FOLDER' => $this->arParams['SEF_FOLDER'], 'URL_TEMPLATES' => $templates, 'VARIABLES' => $variables];
$this->includeComponentTemplate($page);   // templates/.default/list.php, detail.php
```

Links: `CComponentEngine::makePathFromTemplate($folder . $templates['detail'], ['ELEMENT_ID' => $id])`. The folder must reach the page (`urlrewrite.php` rule added when the component is saved in the editor). New sections: prefer controllers + `/local/routes` (`bitrix-routing`); complex SEF components only when the visual editor must manage the section.

## AJAX: `Controllerable`

```php
final class VendorCatalogListComponent extends \CBitrixComponent
    implements \Bitrix\Main\Engine\Contract\Controllerable, \Bitrix\Main\Errorable
{
    private \Bitrix\Main\ErrorCollection $errorCollection;

    public function onPrepareComponentParams($arParams): array
    {
        $this->errorCollection = new \Bitrix\Main\ErrorCollection(); // runs for AJAX too
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);

        return $arParams;
    }

    public function configureActions(): array
    {
        return ['addToCart' => []];   // [] keeps the default prefilters (`bitrix-framework`)
    }

    public function addToCartAction(int $productId): array
    {
        return ['ok' => true];        // executeComponent() is not called on AJAX
    }

    protected function listKeysSignedParameters(): array
    {
        return ['IBLOCK_ID'];         // params restored on AJAX from signedParameters
    }

    public function getErrors(): array { return $this->errorCollection->toArray(); }
    public function getErrorByCode($code) { return $this->errorCollection->getErrorByCode($code); }
}
```

```javascript
BX.ajax.runComponentAction('vendor:catalog.list', 'addToCart', {
    mode: 'class',
    signedParameters: <?= \Bitrix\Main\Web\Json::encode($this->getComponent()->getSignedParameters()) ?>,
    data: { productId: 42 },
});
```

Filters, errors and response types: `bitrix-controllers`. Legacy `AJAX_MODE = 'Y'` (compat) refreshes the whole component and updates `#pagetitle` / `#navigation` in the site template.
