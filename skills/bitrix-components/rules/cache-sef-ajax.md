# Кеш, постраничные списки, ЧПУ, AJAX

## Кеш результата

ID кеша включает сайт, язык, шаблон сайта, компонент, шаблон компонента, все параметры без `~`, смещение часового пояса и второй аргумент `startResultCache()`. Добавь остальные значения, влияющие на вывод:

```php
if ($this->startResultCache(false, [$GLOBALS['USER']->GetUserGroupArray()])) { … }
```

- `CACHE_TYPE = 'N'` отключает кеш; `'A'` следует опции main `component_cache_on`.
- При попадании в кеш `result_modifier.php` и `template.php` не выполняются; в `component_epilog.php` попадут только ключи из `setResultCacheKeys()` (`rules/template.md`).
- Сброс по тегу: `bitrix-caching`.

## Постраничный список

Создай `PageNavigation` до кеша, включи страницу в ключ; иначе все страницы покажут закешированную первую. Считай строки до `initFromUri()`: завышенные номера ограничатся, а не создадут отдельные записи кеша.

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

Если разрешён `page-all`, включи `$nav->allRecordsShown()` в ключ. Подробности `PageNavigation` и своя разметка навигации: `bitrix-cms-basics` (`rules/page-navigation.md`).

## ЧПУ комплексных компонентов

`.parameters.php` объявляет страницы: `'SEF_MODE' => ['list' => ['NAME' => …, 'DEFAULT' => '', 'VARIABLES' => []], 'detail' => ['NAME' => …, 'DEFAULT' => '#ELEMENT_ID#/', 'VARIABLES' => ['ELEMENT_ID']]]`; при вызове передаются `SEF_MODE = 'Y'`, `SEF_FOLDER`, `SEF_URL_TEMPLATES`.

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

Ссылки: `CComponentEngine::makePathFromTemplate($folder . $templates['detail'], ['ELEMENT_ID' => $id])`. Папка должна вести на страницу (правило `urlrewrite.php` добавляется при сохранении компонента в редакторе). Для новых разделов предпочитай контроллеры + `/local/routes` (`bitrix-routing`); комплексный ЧПУ — если разделом управляет визуальный редактор.

## AJAX: `Controllerable`

```php
final class VendorCatalogListComponent extends \CBitrixComponent
    implements \Bitrix\Main\Engine\Contract\Controllerable, \Bitrix\Main\Errorable
{
    private \Bitrix\Main\ErrorCollection $errorCollection;

    public function onPrepareComponentParams($arParams): array
    {
        $this->errorCollection = new \Bitrix\Main\ErrorCollection(); // выполняется и для AJAX
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);

        return $arParams;
    }

    public function configureActions(): array
    {
        return ['addToCart' => []];   // [] сохраняет фильтры по умолчанию (bitrix-framework)
    }

    public function addToCartAction(int $productId): array
    {
        return ['ok' => true];        // executeComponent() при AJAX не вызывается
    }

    protected function listKeysSignedParameters(): array
    {
        return ['IBLOCK_ID'];         // параметры восстанавливаются из signedParameters
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

Фильтры, ошибки и типы ответа: `bitrix-controllers`. Старый `AJAX_MODE = 'Y'` обновляет весь компонент и `#pagetitle` / `#navigation` шаблона сайта; оставляй для совместимости.

Подпись параметров AJAX-компонента и подпись пошагового экспорта — разные контракты. Для экспорта прочитай `bitrix-controllers`, rules/errors-response.md, раздел «Подпись экспорта».
