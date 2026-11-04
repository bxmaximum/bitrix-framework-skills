# События, кеш и безопасность

## События

Девять типов, константы `DataManager::EVENT_ON_*`: `OnBeforeAdd`, `OnAdd`, `OnAfterAdd`, `OnBeforeUpdate`, `OnUpdate`, `OnAfterUpdate`, `OnBeforeDelete`, `OnDelete`, `OnAfterDelete`.

| Параметр | Add | Update | Delete |
| --- | --- | --- | --- |
| `fields` | Да | Да | Нет |
| `object` | Да (клон в On/After) | Да (клон в On/After) | Да (клон) |
| `primary` (+ устаревший `id`) | Только After | Да | Да |

Применяются только результаты `OnBefore*`: `modifyFields()`, `unsetFields()`/`unsetField()` (Add/Update), `addError()` (все три). Ошибка отменяет запись.

### Внутри таблета

Статический метод с именем события (`onBeforeAdd`) выполняется только для события с пространством имён:

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
        $result->addError(new FieldError($event->getEntity()->getField('TITLE'), 'Нужен заголовок'));
        return $result;
    }

    $result->modifyFields(['TITLE' => trim($fields['TITLE'])]);

    return $result;
}
```

### Из другого модуля

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventManager;

EventManager::getInstance()->addEventHandler(
    PostTable::class,
    DataManager::EVENT_ON_AFTER_UPDATE,
    static function (Event $event): void { /* ... */ },
);
// постоянная регистрация при установке модуля:
EventManager::getInstance()->registerEventHandler(PostTable::class, DataManager::EVENT_ON_AFTER_UPDATE, 'vendor.other', OnPostUpdate::class, 'handle');
```

- Имя события `\Vendor\Module\Post::OnAfterUpdate`, модуль определяется по пространству имён (`vendor.module`). Также отправляется устаревшее событие `PostOnAfterUpdate` с меньшим числом параметров. Неизвестный тип вызывает `ArgumentException`.
- Сущности highloadblock отправляют события в модуле `''`: `bitrix-highloadblock`.

## Кеш запросов

```php
PostTable::query()->setSelect(['ID', 'TITLE'])->setCacheTtl(3600)->cacheJoins(true)->fetchAll();
PostTable::cleanCache();              // после прямого SQL, обошедшего запись через ORM
```

- `isCacheable()` по умолчанию `true`. Кеш хранится в каталоге управляемого кеша `orm_<table>`; записи ORM очищают его. Тегов TaggedCache `ORM_*` нет.
- `cache_flags` в `.settings.php`: `<table>_max_ttl`, `<table>_min_ttl` ограничивают TTL; `<table>_max_ttl => 0` отключает кеш. Подробнее: `bitrix-caching`.

`cacheJoins(true)` разрешает кеширование запроса с JOIN, но не связывает сброс кеша основной сущности с записью в присоединённые таблицы. При их изменении очищай кеш основной сущности самостоятельно либо не кешируй такой результат.

## Пользовательский ввод в запросах

`select`, `order`, `group`, `runtime`, `ExpressionField` небезопасны для ввода: сверяй имена с разрешённым списком. Значения фильтра экранируются, его ключи (имена полей) не проверяются: разрешённый список нужен и для них.

```php
$by = in_array($requestBy, ['ID', 'CREATED_AT', 'TITLE'], true) ? $requestBy : 'ID';
PostTable::query()->setSelect(['ID'])->setOrder([$by => 'DESC'])->fetchAll();
```

Доступ к строкам: держи фильтр прав отдельным элементом, чтобы пользовательские ключи или `'LOGIC' => 'OR'` не могли его заменить. Тот же фильтр нужен для подсчёта:

```php
$filter = [$userFilter, $accessFilter];            // элементы верхнего уровня соединяются AND; не array_merge()
$rows = PostTable::getList(['select' => ['ID'], 'filter' => $filter])->fetchAll();
$total = PostTable::getCount($filter);
```

Полные схемы: `bitrix-security`.
