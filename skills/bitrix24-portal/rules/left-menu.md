# Left menu

There is **no public PHP API** for left-menu items. `bitrix:menu` (template `left_vertical`) builds the menu from menu files plus saved items; `Bitrix\Intranet\UI\LeftMenu\Menu` merges them on every hit.

| Source | Storage | Who adds |
| --- | --- | --- |
| System items | `/.top.menu_ext.php` in the site root (product file) | product |
| Items for all users | option `intranet` / `left_menu_items_to_all_<SITE_ID>` (serialized) | admin in UI: item menu → add for all |
| REST app items | option `intranet` / `left_menu_items_marketplace_<SITE_ID>` | app install (`LEFT_MENU` placement) |
| Personal items | user option `intranet` / `left_menu_self_items_<SITE_ID>` | each user in UI |

- `/.top.menu_ext.php` overwrites `$aMenuLinks` completely, so items in `/.top.menu.php` never reach the left menu. Don't patch the product `_ext` file.
- Preferred: add the page in the UI ("add to left menu" → for all users). No code, survives updates.

## Deploy automation (internal format)

A migration can write the same structure the admin UI writes (action `addItemToAll` of `Bitrix\Intranet\Controller\LeftMenu`). The format is internal; re-check after `intranet` updates. The UI stores the option per site — read and write with the same `$siteId`, or your value is shadowed.

```php
$name = 'left_menu_items_to_all_' . $siteId;
$items = unserialize(\Bitrix\Main\Config\Option::get('intranet', $name, '', $siteId), ['allowed_classes' => false]) ?: [];
if (!in_array('vendor_reports', array_column($items, 'ID'), true))
{
    $items[] = ['ID' => 'vendor_reports', 'TEXT' => 'Reports', 'LINK' => '/my-reports/'];
    \Bitrix\Main\Config\Option::set('intranet', $name, serialize($items), $siteId);
}
```

- Item keys: `ID` (unique), `TEXT`, `LINK`; optional `NEW_PAGE => 'Y'`, `COUNTER_ID`.
- Tools switched off in portal settings hide their items (`Bitrix\Intranet\Settings\Tools\ToolsManager::checkAvailabilityByMenuId()`), regardless of storage.
- Extranet site and collabers get their own menu/preset; add items per `SITE_ID`.
