# Панель страницы (`Bitrix\UI\Toolbar`)

Стандартная шапка: заголовок, кнопки, main.ui.filter, избранное. PHP-фасад `Bitrix\UI\Toolbar\Facade\Toolbar`, компонент bitrix:ui.toolbar. JS-расширения ui.toolbar нет: не вызывай `Extension::load()` / import. Клиентский API — глобальный BX.UI.ToolbarManager из шаблона компонента.

```php
use Bitrix\Main\Loader;
use Bitrix\UI\Buttons\CreateButton;
use Bitrix\UI\Toolbar\ButtonLocation;
use Bitrix\UI\Toolbar\Facade\Toolbar;

Loader::includeModule('ui');

Toolbar::setTitle('Заказы'); // только заголовок панели, не <title>
Toolbar::addFilter([ // параметры bitrix:main.ui.filter
    'FILTER_ID' => 'orders_grid', 'GRID_ID' => 'orders_grid',
    'FILTER' => $filterFields, 'ENABLE_LABEL' => true,
]);
Toolbar::addButton(CreateButton::create(['link' => '/local/orders/new/']), ButtonLocation::AFTER_TITLE);
Toolbar::addButton(['text' => 'Экспорт', 'click' => 'BX.Vendor.Orders.export']); // массив → Button
```

- Вызывай фасад после пролога на странице, в классе / шаблоне компонента: панель выводится в конце сборки страницы. Состояние живёт один запрос.
- Шаблоны Битрикс24 и bitrix:ui.sidepanel.wrapper уже включают bitrix:ui.toolbar. Сам подключай только в своём шаблоне сайта, административной странице (шаблон admin) либо для второй панели. У своей шапки: `Toolbar::disable()`.
- Фасад направляет вызовы через __callStatic к `Toolbar::DEFAULT_ID` (default-toolbar).

## Кнопки

- `addButton(Button|array $button, $location = ButtonLocation::RIGHT)`; не-Button вызывает ArgumentTypeException. Позиции: RIGHT, AFTER_TITLE (основное «Создать»), AFTER_FILTER (только с фильтром). FILTER_RIGHT — устаревший псевдоним AFTER_FILTER.
- `deleteButtons(fn (Button $button, string $location): bool)`, true удаляет; `shuffleButtons(fn (array $buttons): array, $location)` для RIGHT / AFTER_FILTER; `getButtons()`.
- На узких экранах кнопки сворачиваются в иконки: кнопке без иконки нужен `'dataset' => ['toolbar-collapsed-icon' => Icon::DOWNLOAD]`.
- При определённой AIR_SITE_TEMPLATE кнопки автоматически получают стиль Air (**Since ui 26.200\***).

## Заголовок, HTML-слоты, дополнения

| Метод | Особенности |
| --- | --- |
| `setTitle()`, `hideTitle()`, `setTitleMaxWidth()` | По умолчанию `$APPLICATION->GetTitle()` |
| addEditableTitle(?string $default) | **Since ui 26.200\***; не сохраняется: обработай JS finishEditing |
| `addBeforeTitleHtml()`, `addAfterTitleHtml()`, `addUnderTitleHtml()`, addRightCustomHtml($html, ['align' => 'right']) | Сырой HTML; значение слота заменяется новым вызовом, данные пользователя экранируй |
| setFilter(string $html) | Своя разметка вместо `addFilter()` |
| `addFavoriteStar()`, `deleteFavoriteStar()` | По умолчанию включено |
| setCopyLinkButton(['link' => ..., 'title' => ...]), setCopyLinkButton(null) | **Since ui 26.200\*** |
| `addFullscreenButton()`, `removeFullscreenButton()` | **Since ui 26.500\***; стандарт из ui:toolbar_fullscreen_button |

Docblock фасада упоминает `getCopyLinkButtonParams()`, реальный метод `getCopyLinkButton()`. На старом ui защищай необязательные вызовы `method_exists(\Bitrix\UI\Toolbar\Toolbar::class, 'setCopyLinkButton')`: у фасада только __callStatic.

## Вторая панель

```php
use Bitrix\UI\Toolbar\Manager;

$toolbar = Manager::getInstance()->createToolbar('orders-secondary', ['filter' => $filterParams]);
$toolbar->addButton(['text' => 'Обновить']);
$APPLICATION->IncludeComponent('bitrix:ui.toolbar', '', ['TOOLBAR_ID' => 'orders-secondary']);
```

## JS

```js
const toolbar = BX.UI.ToolbarManager.getDefaultToolbar(); // либо get(id); null при отсутствии
toolbar?.getButton('orders-export')?.setWaiting(true); // ID из $button->setUniqId()
toolbar?.subscribe('finishEditing', (event) => saveTitle(event.getData().updatedTitle));
```

`getButtons()`, `getContainer()`; `setTitle()`, `getRightButtonsContainer()`, `getTitleEditor()`, события beforeStartEditing (preventDefault() отменяет), startEditing, finishEditing (BX.UI.Toolbar) — **Since ui 26.200\***. Раньше только `getButton()` / `getButtons()`.
