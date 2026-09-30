# Grid, filter, group actions

`bitrix:main.ui.grid` and `bitrix:main.ui.filter` render the UI and store per-user settings only. Your code filters, sorts, pages and checks rights before `IncludeComponent()`. Link them by passing the same id as `FILTER_ID` and `GRID_ID`. With a toolbar, put the filter there: `Toolbar::addFilter()` (`rules/toolbar.md`).

```php
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;

$gridId = 'orders_grid';
$filterFields = [
    ['id' => 'FIND', 'name' => 'Search'],
    ['id' => 'STATUS', 'name' => 'Status', 'type' => 'list', 'items' => ['' => 'Any', 'new' => 'New']],
];
$filter = (new FilterOptions($gridId))->getFilter($filterFields); // user values; whitelist keys yourself
$gridOptions = new GridOptions($gridId);
$sort = $gridOptions->getSorting(['sort' => ['ID' => 'DESC']])['sort']; // user-saved too: whitelist fields

$nav = new PageNavigation($gridId);
$nav->allowAllRecords(false)->setPageSize($gridOptions->GetNavParams()['nPageSize']);
$nav->initFromUri();
// ORM query: filter from $filter, order $sort, limit/offset from $nav; then $nav->setRecordCount($total)

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

## Rows and columns

- Cells print `columns[ID]`, else `data[ID]`, **both as raw HTML**: escape with `htmlspecialcharsbx()`. Keep raw values for inline edit under `data['~ID']`.
- Row `actions`: `[['text' => 'Edit', 'onclick' => "BX.SidePanel.Instance.open('/local/orders/42/')", 'default' => true]]`. `onclick` is JS source: build it with `CUtil::JSEscape()` / `Json::encode()`, never raw input. `default` runs on double click.
- `COLUMNS` in new code (`HEADERS` is compat); `ROW_LAYOUT` disables column/row sorting.
- Grid settings are stored by `GRID_ID`: renaming the id loses users' columns and presets.

## Filter fields

- Types: `string`, `textarea`, `list` (multiple: `'params' => ['multiple' => 'Y']`), `number`, `date` (`'time' => true`; hide subtypes via `exclude` + `Bitrix\Main\UI\Filter\DateType::*`), `custom_date`, `entity_selector` (`'params' => ['dialogOptions' => [...]]`; `addEntityIdToResult => 'Y'` when several entities share a field; `rules/selectors.md`). Prefer `entity_selector` over `dest_selector` / `custom_entity`.
- Ranges come back as suffixed keys: `_from`, `_to`, `_datesel`, `_numsel`. `getSearchString()` returns the search box text.
- Presets: `'FILTER_PRESETS' => ['my' => ['name' => 'Mine', 'fields' => [...], 'default' => true]]`.
- JS: `BX.addCustomEvent('BX.Main.Filter:apply', (filterId, data, filter) => …)` for lists that are not a linked grid; `BX.Main.filterManager.getById(id).getApi().setFields({...})` then `.apply()`.

## Group actions

```php
use Bitrix\Main\Grid\Panel\Snippet;

$snippet = new Snippet();
$groupParams = [ // merge into the main.ui.grid params
    'SHOW_ROW_CHECKBOXES' => true, 'SHOW_SELECTED_COUNTER' => true, 'SHOW_ACTION_PANEL' => true,
    'ACTION_PANEL' => ['GROUPS' => [['ITEMS' => [$snippet->getRemoveButton(), $snippet->getEditButton()]]]],
];
```

- `getRemoveButton()` posts back to the page `action_button_<GRID_ID>=delete`, `ID[]` and `sessid`: handle it before rendering with `check_bitrix_sessid()` and a per-row rights check.
- JS: `BX.Main.gridManager.reload(gridId)`; `BX.Main.gridManager.getInstanceById(gridId).getRows().getSelectedIds()`.
- Floating panel for your own lists: `ui.action-panel` → `ActionPanel` (**Since ui 26.675**; global `BX.UI.AirActionPanel`, because legacy `ui.actionpanel` owns `BX.UI.ActionPanel`):

```js
import { ActionPanel } from 'ui.action-panel';

const panel = new ActionPanel({
    renderTo: () => document.getElementById('orders-list'),
    items: [{ id: 'delete', text: 'Delete', onclick: () => deleteSelected() }], // text is plain text
    onResetAll: () => resetSelection(),
});
panel.setTotalSelectedItems(3);
panel.show();
```

Older ui: `ui.actionpanel` (`BX.UI.ActionPanel`). Load it by id only: since ui 26.675 its files are bundled (`panel.js`, `item.js` are gone) and internals such as `getMutationObserver()`, `addHiddenItem()` were removed; don't override its methods. Drawing a second panel into the same container destroys the first.
