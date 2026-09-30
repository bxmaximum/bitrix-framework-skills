# Pages: properties, sections, menus, includes

## Page and directory properties

```php
$APPLICATION->SetTitle('About');
$APPLICATION->SetPageProperty('description', 'About the company');
$title = $APPLICATION->GetPageProperty('title', 'Default');
$desc = $APPLICATION->GetProperty('description');   // page → directory → default
```

- `SetPageProperty` / `GetPageProperty` — current page; `SetDirProperty` / `GetDirProperty` — directory (usually from `.section.php`); `GetProperty` — page, then directory.
- Keys: `title`, `description`, `keywords`, `robots`, plus own uppercase IDs. SEO meta: `bitrix-seo`.
- Set properties in the page, a component's `component_epilog.php` or its class — never only in a cached template (`bitrix-components`).

## `.section.php` and `.access.php`

```php
<?php
$sSectionName = 'News';                          // breadcrumb title
$arDirProperties = ['description' => 'Company news'];
```

The kernel walks from the current directory up to the site root. `.access.php` holds per-directory file rights (`PERM[...]`); change them via Admin or `$APPLICATION->SetFileAccessPermission($path, $permissions)`, not by hand.

## Menus

- Types per site (`top`, `left`, …) in main module settings; items in `.<type>.menu.php` (`$aMenuLinks`) in the section folder; dynamic items in `.<type>.menu_ext.php`.
- Output: `bitrix:menu` with `ROOT_MENU_TYPE`, `MAX_LEVEL`, `CHILD_MENU_TYPE`, `USE_EXT`.

## Include areas

```php
$APPLICATION->IncludeComponent('bitrix:main.include', '', [
    'AREA_FILE_SHOW' => 'file',                   // file | page | sect
    'PATH' => SITE_DIR . 'include/phone.php',
]);
```

`page` / `sect` look for `<page>_inc.php` / `sect_inc.php` (`AREA_FILE_SUFFIX`, default `inc`; `AREA_FILE_RECURSIVE` searches parent sections). Editable in the public edit mode.

## Breadcrumbs

From `$sSectionName` along the path, plus `$APPLICATION->AddChainItem($title, $url)`; output `bitrix:breadcrumb`.

## Users, groups, admin pages

- Users: read `\Bitrix\Main\UserTable` (read-only), write `CUser`; group membership `CUser::GetUserGroup($id)`, `$USER->GetUserGroupArray()`.
- Module rights: `\CMain::GetUserRight($moduleId)` (`'D'` = denied … `'W'` = full); file rights: `.access.php`.
- Admin pages: module `admin/` scripts included from `/bitrix/admin/` stubs (`bitrix-modules`); modern lists/forms: `bitrix-ui`; legacy `CAdminList` / `CAdminForm` compat only.
