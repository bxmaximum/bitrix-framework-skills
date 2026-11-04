# Страницы портала, слайдер и панель

## Размещение файлов

| Содержимое | Путь |
| --- | --- |
| Компонент: логика и шаблон | `/local/components/<vendor>/<name>/` |
| Сервисы, контроллеры, события | `/local/modules/<vendor.module>/` |
| Публичный файл по URL | корень сайта портала (`SITE_DIR`, обычно `/`), например `/my-reports/index.php` |
| Свой шаблон компонента портала | `/local/templates/bitrix24/components/bitrix/<component>/<template>/` (`bitrix-components`) |

- Публичный файл содержит header, один `IncludeComponent`, footer. Логику размещай в компоненте/сервисах.
- Не редактируй `/bitrix/templates/bitrix24/` и публичные файлы продукта (`/.top.menu_ext.php`, `/company/…`): обновление их заменяет.
- Прямые URL с параметрами (`/my-reports/edit.php?id=5`) упрощают правила слайдера. Для ЧПУ — `/local/routes` и `Bitrix\Main\Routing\Controllers\PublicPageController` с публичным файлом (`bitrix-routing`); JSON/AJAX — контроллеры (`bitrix-controllers`). Продуктовые правила `urlrewrite.php` идут раньше маршрутов. Не занимай их пути и не добавляй новые правила туда.

## Страница в оформлении портала

```php
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
/** @global CMain $APPLICATION */
$APPLICATION->SetTitle('Reports');
$APPLICATION->IncludeComponent('vendor:report.list', '', ['PATH_TO_EDIT' => '/my-reports/edit.php?id=#ID#']);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
```

## Страница в слайдере

```php
$APPLICATION->IncludeComponent('bitrix:ui.sidepanel.wrapper', '', [
    'POPUP_COMPONENT_NAME' => 'vendor:report.edit',
    'POPUP_COMPONENT_TEMPLATE_NAME' => '',
    'POPUP_COMPONENT_PARAMS' => ['ID' => $id],
    'PAGE_MODE' => false,                       // прямой URL открывает слайдер поверх обратной страницы
    'PAGE_MODE_OFF_BACK_URL' => '/my-reports/',
]);
```

- Слайдер запрашивает `IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`. Шаблон `bitrix24` пропускает header/footer при `IFRAME=Y`, включая `<html>` и ресурсы, поэтому без обёртки страница будет неполной.
- Обёртка вызывает `RestartBuffer()`, печатает целый документ и завершает запрос через `exit`. Используй из публичного файла, не внутри ответа контроллера.
- `PAGE_MODE` по умолчанию `true`: прямой URL показывает обычную страницу с компонентом.
- Параметры: `HIDE_TOOLBAR`/`PLAIN_VIEW` (панель включена по умолчанию; `USE_UI_TOOLBAR` только для совместимости), `USE_PADDING`, `USE_BACKGROUND_CONTENT`, `BUTTONS` для `bitrix:ui.button.panel` (`save`, `apply`, `cancel`, `close`), `CLOSE_AFTER_SAVE`/`RELOAD_GRID_AFTER_SAVE`/`RELOAD_PAGE_AFTER_SAVE` после POST, перенаправления и GET.
- В `POPUP_COMPONENT_PARAMS` всегда добавляется `IFRAME => true`.

## JavaScript SidePanel

Открытие, `bindAnchors`, `postMessage`/`SidePanel.Slider:onMessage`, события и параметры: `bitrix-ui`, `rules/popups-sidepanel.md`. Особенности портала:

- В iframe слайдера `BX.SidePanel.Instance` — менеджер верхнего окна. Через него открывай вложенные слайдеры вместо `window.open`.
- Правило `bindAnchors` (`condition: ['^/my-reports/edit\\.php']`) открывает ссылки в слайдере. Без него обычная ссылка перезагрузит портал.
- Обновляй открывшее окно сообщением `postMessage`/`onMessage`, а не `top.location.reload()`.

## Панель страницы

Шаблон выводит `bitrix:ui.toolbar` отложенным буфером. Фасад вызывай до конца страницы: в `class.php` компонента или шаблоне.

```php
\Bitrix\UI\Toolbar\Facade\Toolbar::addFilter([
    'FILTER_ID' => $gridId, 'GRID_ID' => $gridId, 'FILTER' => $fields, 'ENABLE_LABEL' => true,
]);
\Bitrix\UI\Toolbar\Facade\Toolbar::addButton(
    ['text' => 'Добавить', 'color' => \Bitrix\UI\Buttons\Color::SUCCESS, 'link' => '/my-reports/edit.php'],
    \Bitrix\UI\Toolbar\ButtonLocation::AFTER_TITLE,
);
\Bitrix\UI\Toolbar\Facade\Toolbar::addFavoriteStar();
```

- `addButton()` принимает `Bitrix\UI\Buttons\Button` либо массив параметров (`text`, `color`, `link`, `click`, `icon`, …). Позиции `Bitrix\UI\Toolbar\ButtonLocation`: `RIGHT` (по умолчанию), `AFTER_TITLE`, `AFTER_FILTER`.
- `addFilter()` уже подключает `bitrix:main.ui.filter`; не выводи компонент фильтра повторно.
- Методы фасада разрешаются через `__callStatic` и `@method` класса `Bitrix\UI\Toolbar\Facade\Toolbar`. Реализация — `Bitrix\UI\Toolbar\Toolbar`.

## Проверка

- [ ] В публичном файле нет логики; код в `/local/components` или `/local/modules`.
- [ ] Страница слайдера использует `bitrix:ui.sidepanel.wrapper`.
- [ ] Кнопки и фильтр добавлены через `Toolbar::*`, без собственного HTML.
- [ ] Слайдер общается с открывшим окном через `postMessage`/`SidePanel.Slider:onMessage`, без `top.location.reload()`.
