# Управляемый кеш

```php
$managed = Application::getInstance()->getManagedCache();
if ($managed->read(86400, 'rates', 'vendor_rates'))
{
    $rates = $managed->get('rates');
}
else
{
    $rates = $this->loadRates();
    $managed->set('rates', $rates);          // setImmediate() пишет сейчас, set() — в конце хита
}
$managed->cleanDir('vendor_rates');
```

`set()`/`setImmediate()` ничего не делают для ID без предшествующего `read()`.

## Кеш компонента

```php
if ($this->startResultCache(false, [$userGroupsKey]))  // CACHE_TIME из параметров; дополнительные части ключа
{
    $this->arResult['ITEMS'] = $this->loadItems();
    if (!$this->arResult['ITEMS'])
    {
        $this->abortResultCache();
    }
    else
    {
        Application::getInstance()->getTaggedCache()->registerTag('vendor_blog_posts');
    }
    $this->setResultCacheKeys(['TITLE']);              // ключи arResult для component_epilog.php
    $this->includeComponentTemplate();
}
```

- Параметры: `CACHE_TYPE` (`A` следует глобальной настройке, `Y`, `N`), `CACHE_TIME`. Ключ уже включает сайт, язык, шаблон, все параметры и смещение часового пояса пользователя; `CACHE_GROUPS` — лишь соглашение: добавляй группы в дополнительный ключ самостоятельно.
- Компонент открывает область тегов для своего пути кеша только при определённой `BX_COMP_MANAGED_CACHE`.
