# Selectors: `ui.entity-selector`, `ui.entity-catalog`

## Entity selector

`import { Dialog, TagSelector } from 'ui.entity-selector'` (global `BX.UI.EntitySelector`). `Dialog` is a popup picker, `TagSelector` a field showing selected items as tags. An item is identified by `entityId` + `id`; a local item needs `{ id, entityId, title }`.

```js
import { Dialog } from 'ui.entity-selector';

const dialog = new Dialog({
    targetNode: button,                 // existing DOM node
    context: 'VENDOR_ORDER_RESPONSIBLE', // own context per field: recent items don't mix
    multiple: false,
    entities: [{ id: 'user' }, { id: 'project', dynamicLoad: true, dynamicSearch: true }],
    events: { 'Item:onSelect': (event) => { const { item } = event.getData(); setValue(item.getId()); } },
});
button.addEventListener('click', () => dialog.show());
```

- `TagSelector`: `new TagSelector({ multiple: true, dialogOptions: { ...same as Dialog } })`, then `selector.renderTo(container)`; `dialogOptions.preselectedItems: [['user', 1]]` loads selected items from the server.
- Entity ids come from module `.settings.php` key `ui.entity-selector` (iblock: `iblock-element`; catalog: `product`, `store`; Bitrix24 modules: `user`, `department`, `project`, `meta-user`, …). Check the module's file for the exact id.
- `dynamicLoad` / `dynamicSearch` work only with a registered, available PHP provider.
- In `main.ui.filter`: field type `entity_selector` with `params.dialogOptions` (`rules/grid-filter.md`).

### Custom provider

```php
namespace Vendor\Orders\Integration\UI\EntitySelector;

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\UI\EntitySelector\BaseProvider;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\Item;
use Bitrix\UI\EntitySelector\SearchQuery;

final class ProjectProvider extends BaseProvider
{
    public function __construct(array $options = []) // must be public: created via newInstance($options)
    {
        parent::__construct();
        $this->options['onlyActive'] = (bool)($options['onlyActive'] ?? true);
    }

    public function isAvailable(): bool
    {
        return CurrentUser::get()->getId() > 0; // real rights check here
    }

    public function getItems(array $ids): array
    {
        return array_map(fn (array $row) => $this->makeItem($row), $this->load(['@ID' => $ids]));
    }

    public function fillDialog(Dialog $dialog): void
    {
        $dialog->addItems(array_map(fn (array $row) => $this->makeItem($row), $this->load([], 20)));
    }

    public function doSearch(SearchQuery $searchQuery, Dialog $dialog): void
    {
        $rows = $this->load(['%NAME' => $searchQuery->getQuery()], 20);
        $dialog->addItems(array_map(fn (array $row) => $this->makeItem($row), $rows));
    }

    private function makeItem(array $row): Item
    {
        return new Item(['id' => (int)$row['ID'], 'entityId' => 'vendor-project', 'title' => $row['NAME']]);
    }

    private function load(array $filter, int $limit = 0): array
    {
        return []; // ORM query with rights filter
    }
}
```

Register in the module `.settings.php`:

```php
'ui.entity-selector' => [
    'value' => [
        'entities' => [[
            'entityId' => 'vendor-project',
            'provider' => ['moduleId' => 'vendor.orders', 'className' => ProjectProvider::class],
        ]],
    ],
    'readonly' => true,
],
```

JS `entities[].id` must equal `entityId`. The provider is the only access check: filter by rights in `getItems()`, `fillDialog()`, `doSearch()`.

## Entity catalog

Popup picker over a grouped catalog (templates, robots). Vue 3 app. `import { EntityCatalog } from 'ui.entity-catalog'` (global `BX.UI.EntityCatalog`); or lazy `const { EntityCatalog } = await Runtime.loadExtension('ui.entity-catalog')`.

```js
const catalog = new EntityCatalog({
    title: 'Add a block',                       // escaped
    showSearch: true,
    groups: [{ id: 'docs', name: 'Documents', selected: true }],
    items: [{ id: 'invoice', title: 'Invoice', groupIds: ['docs'], button: {
        text: 'Add',
        action: (event) => { addBlock(event.getData().eventData.id); catalog.close(); }, // stays open otherwise
    } }],
});
catalog.show();
```

- Group `icon` is raw HTML and `slots` are Vue template strings compiled unescaped: never build them from user or server data.
- `close()` destroys the popup with its subscriptions: pass `popupOptions.events` or re-subscribe after each `show()`.
- `setItems()` does not redraw the open group; `updateItemById(id, patch)` and `updateGroupCounter(groupId, n)` do (**Since ui 26.200\***; older: recreate the catalog).
- Search: title/description substring + exact tag match. Filters: `filterOptions: { filterItems: [{ id, text, action: (item) => bool }], multiple }`.
- `disabled` / `locked` are visual only: check again inside `action`.
