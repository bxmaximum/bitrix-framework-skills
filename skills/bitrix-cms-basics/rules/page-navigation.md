# Постраничная навигация

`\Bitrix\Main\UI\PageNavigation` только вычисляет параметры по URL: без БД и разметки. Подсчитай строки, примени offset/limit, выведи `bitrix:main.pagenavigation`.

## Порядок вызовов

```php
use Bitrix\Main\UI\PageNavigation;

$filter = ['=ACTIVE' => 'Y'];

$nav = new PageNavigation('nav-items');      // параметр URL / сегмент ЧПУ, уникальный на странице
$nav->allowAllRecords(false)
    ->setPageSizes([20, 50, 100])             // допустимые размеры для ?nav-items=page-2-size-50
    ->setPageSize(20)
    ->setRecordCount(ItemTable::getCount($filter)); // фильтр тот же, что у данных
$nav->initFromUri();                          // возвращает void

if ($nav->getPageCount() === 0) {
    return [];                                // при 0 строк страница равна 0, смещение стало бы -20
}

$rows = ItemTable::getList([
    'filter' => $filter,
    'order' => ['ID' => 'DESC'],
    'offset' => $nav->getOffset(),
    'limit' => $nav->getLimit(),
])->fetchAll();
```

- Порядок важен: `allowAllRecords()` → `setPageSizes()` → `setPageSize()` → `setRecordCount()` → `initFromUri()`. `initFromUri()` принимает `size` из URL только из списка `setPageSizes()` (пустой список — игнорировать), `page-all` — только если разрешено. Ограничение номера числом страниц действует, только если количество строк уже задано.
- По умолчанию: размер 20, список размеров пуст, `page-all` запрещён, текущая страница 1.
- URL: GET `?nav-items=page-3-size-20` / `page-all`; ЧПУ `…/nav-items/page-3/size-20/` читается только без GET-параметра. Путь должен вести на нужную страницу.
- `getOffset()` = (страница − 1) × размер; `getLimit()` = размер. В режиме всех строк смещение 0, лимит равен количеству строк; без заданного количества — `null`, запрос без ограничения.
- `setCurrentPage()` и `setPageSize()` не проверяют значения.
- `allowAllRecords(true)` позволяет любому посетителю выгрузить таблицу через `page-all`; не используй на больших таблицах. `allRecordsAllowed()` — режим разрешён, `allRecordsShown()` — включён сейчас.
- Для своего интерфейса: `getRecordCount()`, `getPageCount()`, `getCurrentPage()`, `getPageSize()`, `getPageSizes()`, `getId()`.
- `addParams(Uri $uri, bool $sef, $page, $size = null)` / `clearParams($uri, $sef)` меняют переданный `Uri`; делай `clone` для каждой ссылки.

Подсчёт тем же запросом: `'count_total' => true` в `getList()` (либо `->countTotal(true)`), затем `$nav->setRecordCount($result->getCount())`. Количество приходит после `initFromUri()`, автоматического ограничения страницы уже нет: проверь `getCurrentPage() > getPageCount()`. Без `count_total` вызов `getCount()` бросает исключение.

Большие таблицы без COUNT: `setPageSize(N)`, `initFromUri()`, запрос с `limit = getLimit() + 1`. Если пришло N + 1 строк, следующая страница есть: `setRecordCount(getOffset() + rowCount)`, убери лишнюю строку.

## Другие классы

- `ReversePageNavigation($id, $count)`: количество — обязательный аргумент конструктора; по умолчанию последняя страница (новые сначала, «1» у максимального номера). Число страниц = floor(count / size), остаток добавляется к первой показываемой странице (лимит до 2×size−1). Завышенный номер даёт отрицательное смещение: задай размер до `initFromUri()`, проверяй `setCurrentPage()`. Для схемы без COUNT не подходит.
- `AdminPageNavigation($id)`: размеры 10…500, `page-all` разрешён, `initFromUri()` вызывается в конструкторе; после него только `setRecordCount()`. Для административных списков.
- Контроллеры: автоматически созданный параметр действия `PageNavigation` имеет ID `nav`, размеры 1–50, без количества; см. `bitrix-controllers`.

## `bitrix:main.pagenavigation`

```php
$APPLICATION->IncludeComponent('bitrix:main.pagenavigation', '', [
    'NAV_OBJECT' => $nav,
    'SEF_MODE' => 'N',
    'PAGE_WINDOW' => 5,
]);
```

- Если `NAV_OBJECT` не `PageNavigation`, вывода нет. При ≤ 1 странице (вне режима всех строк) блок скрыт, кроме `SHOW_ALWAYS = 'Y'`.
- `SEF_MODE` / `SHOW_ALWAYS` включаются только значением `'Y'` или `true`; `BASE_LINK` заменяет базовый URL (AJAX-списки).
- Шаблоны: `.default` (`TEMPLATE_THEME = blue|green|yellow|red`), `modern`, `grid`, `admin`. Только `admin` выводит «N–M из TOTAL» (`SHOW_COUNT`) и выбор размера; ссылки вызывают `<TABLE_ID>.GetAdminList()`, поэтому он только для административных страниц.
- Обратная навигация определяется автоматически (`REVERSED_PAGES`).
- Своя разметка: копия шаблона в `/local/templates/<site_template>/components/bitrix/main.pagenavigation/<name>/`. `$arResult`: `RECORD_COUNT`, `PAGE_COUNT`, `CURRENT_PAGE`, `PAGE_SIZE`, `PAGE_SIZES`, `ALL_RECORDS`, `SHOW_ALL`, `REVERSED_PAGES`, `START_PAGE`, `END_PAGE`, `FIRST_RECORD`, `LAST_RECORD`, `URL`; ссылки: `$component->replaceUrlTemplate($page, $size)`.
- В кешируемом компоненте номер страницы включай в ключ кеша: `bitrix-components`.
