# Events, cache, security

## Events

Nine types, constants `DataManager::EVENT_ON_*`: `OnBeforeAdd`, `OnAdd`, `OnAfterAdd`, `OnBeforeUpdate`, `OnUpdate`, `OnAfterUpdate`, `OnBeforeDelete`, `OnDelete`, `OnAfterDelete`.

| Parameter | Add | Update | Delete |
| --- | --- | --- | --- |
| `fields` | yes | yes | no |
| `object` | yes (clone in On/After) | yes (clone in On/After) | yes (clone) |
| `primary` (+ legacy `id`) | After only | yes | yes |

Only `OnBefore*` results are applied: `modifyFields()`, `unsetFields()`/`unsetField()` (Add/Update), `addError()` (all three). An error aborts the write.

### In the tablet

A static method named like the event (`onBeforeAdd`) runs for the namespaced event only:

```php
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\ORM\Fields\FieldError;

public static function onBeforeAdd(Event $event): EventResult
{
    $result = new EventResult();
    $fields = $event->getParameter('fields');

    if (trim((string)($fields['TITLE'] ?? '')) === '')
    {
        $result->addError(new FieldError($event->getEntity()->getField('TITLE'), 'Title is required'));
        return $result;
    }

    $result->modifyFields(['TITLE' => trim($fields['TITLE'])]);

    return $result;
}
```

### From another module

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventManager;

EventManager::getInstance()->addEventHandler(
    PostTable::class,
    DataManager::EVENT_ON_AFTER_UPDATE,
    static function (Event $event): void { /* ... */ },
);
// persistent, on module install:
EventManager::getInstance()->registerEventHandler(PostTable::class, DataManager::EVENT_ON_AFTER_UPDATE, 'vendor.other', OnPostUpdate::class, 'handle');
```

- Event name `\Vendor\Module\Post::OnAfterUpdate`, module derived from the namespace (`vendor.module`). A legacy `PostOnAfterUpdate` event with fewer parameters is sent too. Unknown type → `ArgumentException`.
- Highloadblock entities fire in module `''`: `bitrix-highloadblock`.

## Query cache

```php
PostTable::query()->setSelect(['ID', 'TITLE'])->setCacheTtl(3600)->cacheJoins(true)->fetchAll();
PostTable::cleanCache();              // after raw SQL that bypassed ORM writes
```

- `isCacheable()` defaults to `true`. Cache lives in managed-cache dir `orm_<table>`; ORM writes clean it. There are no `ORM_*` TaggedCache tags.
- `.settings.php` `cache_flags`: `<table>_max_ttl`, `<table>_min_ttl` clamp TTL; `<table>_max_ttl => 0` disables it. Details: `bitrix-caching`.

## User input in queries

`select`, `order`, `group`, `runtime` and `ExpressionField` are not safe for input: whitelist names. Filter values are escaped, filter keys (field names) are not checked: whitelist them too.

```php
$by = in_array($requestBy, ['ID', 'CREATED_AT', 'TITLE'], true) ? $requestBy : 'ID';
PostTable::query()->setSelect(['ID'])->setOrder([$by => 'DESC'])->fetchAll();
```

Row-level access: keep the access filter a separate element so user keys or `'LOGIC' => 'OR'` cannot override it, and reuse it for the count:

```php
$filter = [$userFilter, $accessFilter];            // top-level elements are ANDed; never array_merge()
$rows = PostTable::getList(['select' => ['ID'], 'filter' => $filter])->fetchAll();
$total = PostTable::getCount($filter);
```

Full patterns: `bitrix-security`.
