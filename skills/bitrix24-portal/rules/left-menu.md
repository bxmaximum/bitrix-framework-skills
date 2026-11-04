# Левое меню

Публичного PHP API пунктов левого меню нет. `bitrix:menu` с шаблоном `left_vertical` строит меню из файлов и сохранённых пунктов; `Bitrix\Intranet\UI\LeftMenu\Menu` объединяет их в каждом запросе.

| Источник | Хранение | Кто добавляет |
| --- | --- | --- |
| Системные пункты | `/.top.menu_ext.php` в корне сайта, файл продукта | продукт |
| Общие пункты | сериализованная опция `intranet/left_menu_items_to_all_<SITE_ID>` | администратор: меню пункта, «Добавить для всех» |
| REST-приложения | `intranet/left_menu_items_marketplace_<SITE_ID>` | установка приложения, встройка `LEFT_MENU` |
| Личные пункты | пользовательская опция `intranet/left_menu_self_items_<SITE_ID>` | пользователь в интерфейсе |

- `/.top.menu_ext.php` полностью заменяет `$aMenuLinks`, поэтому `/.top.menu.php` не попадает в левое меню. Не исправляй продуктовый `_ext`.
- Предпочтительно добавление страницы через интерфейс: «Добавить в левое меню», затем для всех. Такой пункт переживает обновления.

## Автоматизация установки: внутренний формат

Миграция может записать структуру действия `addItemToAll` класса `Bitrix\Intranet\Controller\LeftMenu`. Формат внутренний; перепроверяй после обновлений `intranet`. Интерфейс хранит опцию по сайту: используй один `$siteId` при чтении и записи, иначе значение будет перекрыто.

```php
$name = 'left_menu_items_to_all_' . $siteId;
$items = unserialize(\Bitrix\Main\Config\Option::get('intranet', $name, '', $siteId), ['allowed_classes' => false]) ?: [];
if (!in_array('vendor_reports', array_column($items, 'ID'), true))
{
    $items[] = ['ID' => 'vendor_reports', 'TEXT' => 'Отчёты', 'LINK' => '/my-reports/'];
    \Bitrix\Main\Config\Option::set('intranet', $name, serialize($items), $siteId);
}
```

- Ключи: уникальный `ID`, `TEXT`, `LINK`; необязательные `NEW_PAGE => 'Y'`, `COUNTER_ID`.
- Выключенный инструмент портала скрывает свой пункт независимо от хранения (`Bitrix\Intranet\Settings\Tools\ToolsManager::checkAvailabilityByMenuId()`).
- У экстранет-сайта и коллаберов своё меню/набор пунктов. Добавляй отдельно по `SITE_ID`.
