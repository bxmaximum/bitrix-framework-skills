# Селекторы: ui.entity-selector, ui.entity-catalog

## Выбор сущности

Dialog и TagSelector из ui.entity-selector, глобальный BX.UI.EntitySelector. Dialog — всплывающий выбор; TagSelector — поле выбранных тегов. Идентификатор: entityId + id; локальной записи нужны `{ id, entityId, title }`.

```js
import { Dialog } from 'ui.entity-selector';

const dialog = new Dialog({
    targetNode: button,                 // существующий узел DOM
    context: 'VENDOR_ORDER_RESPONSIBLE', // отдельный контекст поля, чтобы не смешивать недавние
    multiple: false,
    entities: [{ id: 'user' }, { id: 'project', dynamicLoad: true, dynamicSearch: true }],
    events: { 'Item:onSelect': (event) => { const { item } = event.getData(); setValue(item.getId()); } },
});
button.addEventListener('click', () => dialog.show());
```

- `new TagSelector({ multiple: true, dialogOptions: { ...dialogOptions } })`, затем selector.renderTo(container). dialogOptions.preselectedItems: [['user', 1]] загружает выбранные записи с сервера.
- ID сущностей заданы в ui.entity-selector файла .settings.php модуля: iblock — iblock-element; catalog — product, store; Битрикс24 — user, department, project, meta-user, …. Точный ID проверь в файле модуля.
- dynamicLoad / dynamicSearch работают только с зарегистрированным доступным PHP-поставщиком.
- В main.ui.filter: тип entity_selector, params.dialogOptions (`rules/grid-filter.md`).

### Свой поставщик

```php
namespace Vendor\Orders\Integration\UI\EntitySelector;

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\UI\EntitySelector\BaseProvider;
use Bitrix\UI\EntitySelector\Dialog;
use Bitrix\UI\EntitySelector\Item;
use Bitrix\UI\EntitySelector\SearchQuery;

final class ProjectProvider extends BaseProvider
{
    public function __construct(array $options = []) // публичный: создаётся через newInstance($options)
    {
        parent::__construct();
        $this->options['onlyActive'] = (bool)($options['onlyActive'] ?? true);
    }

    public function isAvailable(): bool
    {
        return CurrentUser::get()->getId() > 0; // здесь нужна проверка реальных прав
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
        return []; // ORM-запрос с фильтром прав
    }
}
```

Регистрация в .settings.php модуля:

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

JS entities[].id должен равняться entityId. Поставщик — единственная проверка доступа: фильтруй по правам в `getItems()`, fillDialog(), doSearch().

## Каталог сущностей

Всплывающий выбор из группированного каталога (шаблоны, роботы), приложение Vue 3. `import { EntityCatalog } from 'ui.entity-catalog'`, глобальный BX.UI.EntityCatalog; отложенно — `const { EntityCatalog } = await Runtime.loadExtension('ui.entity-catalog')`.

```js
const catalog = new EntityCatalog({
    title: 'Добавить блок', // экранируется
    showSearch: true,
    groups: [{ id: 'docs', name: 'Документы', selected: true }],
    items: [{ id: 'invoice', title: 'Счёт', groupIds: ['docs'], button: {
        text: 'Добавить',
        action: (event) => { addBlock(event.getData().eventData.id); catalog.close(); }, // иначе остаётся открытым
    } }],
});
catalog.show();
```

- icon группы — сырой HTML; slots — строки шаблонов Vue, компилируемые без экранирования. Не собирай из данных пользователя / сервера.
- close() уничтожает окно и подписки: передавай popupOptions.events либо подписывайся после каждого `show()`.
- `setItems()` не перерисовывает открытую группу; updateItemById(id, patch), updateGroupCounter(groupId, n) перерисовывают (**Since ui 26.200\***; раньше пересоздавай каталог).
- Поиск: подстрока title/description, точный тег. Фильтры: `filterOptions: { filterItems: [{ id, text, action: (item) => bool }], multiple }`.
- disabled / locked меняют только вид: проверяй снова в action.
