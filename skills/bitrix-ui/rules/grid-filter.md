# Грид, фильтр, групповые действия

`bitrix:main.ui.grid` и `bitrix:main.ui.filter` выводят интерфейс, хранят настройки пользователя. Код до `IncludeComponent()` фильтрует, сортирует, разбивает на страницы, проверяет права. Свяжи одинаковыми `FILTER_ID` и `GRID_ID`. При панели инструментов фильтр добавляй через `Toolbar::addFilter()` (`rules/toolbar.md`).

```php
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;

$gridId = 'orders_grid';
$filterFields = [
    ['id' => 'FIND', 'name' => 'Поиск'],
    ['id' => 'STATUS', 'name' => 'Статус', 'type' => 'list', 'items' => ['' => 'Любой', 'new' => 'Новый']],
];
$filter = (new FilterOptions($gridId))->getFilter($filterFields); // ввод пользователя: разрешай только нужные ключи
$gridOptions = new GridOptions($gridId);
$sort = $gridOptions->getSorting(['sort' => ['ID' => 'DESC']])['sort']; // сохранено пользователем: проверяй поля

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(false)->setPageSize($gridOptions->GetNavParams()['nPageSize']);
$nav->initFromUri();
// ORM: фильтр из $filter, порядок $sort, limit/offset из $nav; затем $nav->setRecordCount($total)

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $gridId, 'GRID_ID' => $gridId, 'FILTER' => $filterFields, 'ENABLE_LABEL' => true,
]);
$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $gridId,
    'COLUMNS' => [['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true]],
    'ROWS' => $rows,   // ['id' => 42, 'data' => [...], 'columns' => [...], 'actions' => [...]]
    'SORT' => $sort,
    'NAV_OBJECT' => $nav, 'TOTAL_ROWS_COUNT' => $nav->getRecordCount(),
    'SHOW_PAGESIZE' => true, 'PAGE_SIZES' => [['NAME' => '20', 'VALUE' => '20'], ['NAME' => '50', 'VALUE' => '50']],
    'AJAX_MODE' => 'Y', 'AJAX_OPTION_JUMP' => 'N', 'AJAX_OPTION_HISTORY' => 'N',
]);
```

## Строки и столбцы

- Ячейка выводит columns[ID], иначе data[ID], **оба как сырой HTML**: `htmlspecialcharsbx()`. Исходное значение для правки храни в `data['~ID']`.
- Действия строки: `[['text' => 'Изменить', 'onclick' => "BX.SidePanel.Instance.open('/local/orders/42/')", 'default' => true]]`. onclick — исходник JS: собирай через `CUtil::JSEscape()` / `Json::encode()`, без сырого ввода. default выполняется по двойному нажатию.
- В новом коде COLUMNS, HEADERS — совместимость; ROW_LAYOUT отключает сортировку колонок / строк.
- Настройки хранятся по GRID_ID: переименование теряет колонки и наборы пользователя.

## Поля фильтра

- Типы: string, textarea, list (множественный: `'params' => ['multiple' => 'Y']`), number, date (`'time' => true`; скрытие подтипов через exclude + `Bitrix\Main\UI\Filter\DateType::*`), custom_date, entity_selector (`'params' => ['dialogOptions' => [...]]`; addEntityIdToResult => Y при разных сущностях в поле; `rules/selectors.md`). Предпочитай entity_selector старым dest_selector / custom_entity.
- Диапазоны возвращаются с суффиксами _from, _to, _datesel, _numsel. `getSearchString()` возвращает текст строки поиска.
- Наборы: `'FILTER_PRESETS' => ['my' => ['name' => 'Мои', 'fields' => [...], 'default' => true]]`.
- JS для списка без связанного грида: `BX.addCustomEvent('BX.Main.Filter:apply', (filterId, data, filter) => …)`; `BX.Main.filterManager.getById(id).getApi().setFields({...})`, затем `apply()`.

## Групповые действия

```php
use Bitrix\Main\Grid\Panel\Snippet;

$snippet = new Snippet();
$groupParams = [ // добавить к параметрам main.ui.grid
    'SHOW_ROW_CHECKBOXES' => true, 'SHOW_SELECTED_COUNTER' => true, 'SHOW_ACTION_PANEL' => true,
    'ACTION_PANEL' => ['GROUPS' => [['ITEMS' => [$snippet->getRemoveButton(), $snippet->getEditButton()]]]],
];
```

- `getRemoveButton()` отправляет POST страницы с action_button_<GRID_ID>=delete, ID[] и sessid. Обработай до вывода с `check_bitrix_sessid()` и проверкой прав каждой строки.
- JS: `BX.Main.gridManager.reload(gridId)`; `BX.Main.gridManager.getInstanceById(gridId).getRows().getSelectedIds()`.
- Плавающая панель своих списков: `ui.action-panel` → ActionPanel (**Since ui 26.675**; глобальный BX.UI.AirActionPanel, поскольку ui.actionpanel занимает BX.UI.ActionPanel):

```js
import { ActionPanel } from 'ui.action-panel';

const panel = new ActionPanel({
    renderTo: () => document.getElementById('orders-list'),
    items: [{ id: 'delete', text: 'Удалить', onclick: () => deleteSelected() }], // обычный текст
    onResetAll: () => resetSelection(),
});
panel.setTotalSelectedItems(3);
panel.show();
```

Раньше: ui.actionpanel (BX.UI.ActionPanel), загрузка только по ID. **Since ui 26.675** файлы собраны в bundle, panel.js / item.js удалены, как и внутренние `getMutationObserver()`, `addHiddenItem()`. Не переопределяй методы. Вторая панель в том же контейнере уничтожает первую.
