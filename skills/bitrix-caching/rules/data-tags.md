# Кеш данных и теги

```php
use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;

$cache = Cache::createInstance();
$cacheDir = '/vendor_blog/posts';
$cacheId = 'list_' . md5(serialize([$filter, LANGUAGE_ID]));

if ($cache->initCache(3600, $cacheId, $cacheDir))
{
    $data = $cache->getVars();
}
elseif ($cache->startDataCache())
{
    $tagged = Application::getInstance()->getTaggedCache();
    $tagged->startTagCache($cacheDir);                   // тот же каталог, что у кеша данных
    $data = PostTable::query()->setSelect(['ID', 'TITLE'])->setFilter($filter)->fetchAll();

    if ($data === [])
    {
        $tagged->abortTagCache();
        $cache->abortDataCache();
    }
    else
    {
        $tagged->registerTag('vendor_blog_posts');
        $tagged->endTagCache();
        $cache->endDataCache($data);
    }
}

Application::getInstance()->getTaggedCache()->clearByTag('vendor_blog_posts'); // при записи / в обработчике ORM OnAfter*
```

- `clearByTag()` удаляет все **каталоги** кеша, зарегистрированные с тегом. Выделяй тегируемым данным собственный `$cacheDir`.
- Ключ включает всё, что меняет результат: фильтр, язык, сайт, группы пользователя. Не читай `$_SESSION`/`$USER` внутри кешируемого блока без включения зависимости в ключ.
- Имена тегов задаёшь ты. Автоматических тегов `ORM_<TABLE>` нет; кеш ORM хранится в каталогах управляемого кеша `orm_<table>` и очищается `XxxTable::cleanCache()`.
