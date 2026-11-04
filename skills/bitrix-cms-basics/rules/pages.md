# Страницы: свойства, разделы, меню, включаемые области

## Свойства страницы и раздела

```php
$APPLICATION->SetTitle('О компании');
$APPLICATION->SetPageProperty('description', 'О компании');
$title = $APPLICATION->GetPageProperty('title', 'По умолчанию');
$desc = $APPLICATION->GetProperty('description');   // страница → раздел → значение по умолчанию
```

- `SetPageProperty` / `GetPageProperty` — страница; `SetDirProperty` / `GetDirProperty` — раздел (обычно из `.section.php`); `GetProperty` — страница, затем раздел.
- Ключи: `title`, `description`, `keywords`, `robots` и собственные ID в верхнем регистре. Метаданные SEO: `bitrix-seo`.
- Задавай свойства на странице, в `component_epilog.php` или классе компонента, а не только в кешируемом шаблоне (`bitrix-components`).

## `.section.php` и `.access.php`

```php
<?php
$sSectionName = 'Новости';                       // заголовок в цепочке навигации
$arDirProperties = ['description' => 'Новости компании'];
```

Ядро проходит от текущего каталога к корню сайта. `.access.php` хранит права на файлы раздела (`PERM[...]`); меняй их через административный интерфейс или `$APPLICATION->SetFileAccessPermission($path, $permissions)`, а не вручную.

## Меню

- Типы меню сайта (`top`, `left`, …) — в настройках main; пункты — в `.<type>.menu.php` (`$aMenuLinks`) раздела; динамические пункты — в `.<type>.menu_ext.php`.
- Вывод: `bitrix:menu` с `ROOT_MENU_TYPE`, `MAX_LEVEL`, `CHILD_MENU_TYPE`, `USE_EXT`.

## Включаемые области

```php
$APPLICATION->IncludeComponent('bitrix:main.include', '', [
    'AREA_FILE_SHOW' => 'file',                   // режим: file | page | sect
    'PATH' => SITE_DIR . 'include/phone.php',
]);
```

`page` / `sect` ищут `<page>_inc.php` / `sect_inc.php` (`AREA_FILE_SUFFIX`, по умолчанию `inc`; `AREA_FILE_RECURSIVE` включает поиск в родительских разделах). Редактируются в публичном режиме правки.

## Цепочка навигации

Строится по `$sSectionName` вдоль пути и `$APPLICATION->AddChainItem($title, $url)`; вывод — `bitrix:breadcrumb`.

## Пользователи, группы, административные страницы

- Пользователи: чтение `\Bitrix\Main\UserTable` (только чтение), запись `CUser`; группы: `CUser::GetUserGroup($id)`, `$USER->GetUserGroupArray()`.
- Права модуля: `\CMain::GetUserRight($moduleId)` (`'D'` — запрет … `'W'` — полный доступ); права файлов: `.access.php`.
- Административные страницы: скрипты `admin/` модуля подключаются заглушками `/bitrix/admin/` (`bitrix-modules`); современные списки и формы: `bitrix-ui`; `CAdminList` / `CAdminForm` — для совместимости.
