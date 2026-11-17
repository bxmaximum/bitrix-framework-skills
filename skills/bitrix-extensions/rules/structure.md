# Структура, сборка и загрузка расширения

## ID и каталог

Каждая точка ID задаёт уровень каталога (`Extension::getPath()`): `vendor.module.editor` → `js/vendor/module/editor/`. Поиск сначала в `/local/js/`, затем `/bitrix/js/`. Минимум два сегмента из латинских букв, цифр, `_` и `-`; проверка не зависит от регистра. Каталог с точкой (`/local/js/vendor.module/editor/`) по ID не загрузится.

| Код | Расположение |
| --- | --- |
| Проект | `/local/js/vendor/module/editor/` → ID `vendor.module.editor` |
| Тиражный модуль `vendor.module` | `install/js/vendor/module/editor/`, при установке копируется в `/local/js/` |
| Ядро | `/bitrix/js/<module>/<ext>/` (`ui.buttons`); не изменяй. Копия по тому же пути в `/local/js/` перекрывает ядро и перестаёт получать обновления |

```php
public function InstallFiles(): void
{
    CopyDirFiles(__DIR__ . '/js', \Bitrix\Main\Application::getDocumentRoot() . '/local/js', true, true); // 4-й аргумент включает рекурсию
}

public function UnInstallFiles(): void
{
    DeleteDirFilesEx('/local/js/vendor/module'); // DeleteDirFiles() не удаляет вложенные каталоги
}
```

## Структура

```
/local/js/vendor/module/editor/
├── src/index.js         # точка входа ES6+
├── dist/                # готовые сборки: включай в коммит и поставку
├── lang/ru/config.php   # необязательные фразы $MESS
├── bundle.config.js     # тоже поставляй: сервер читает из него namespace
└── config.php
```

Создание заготовки: `bitrix create`; сборка: `bitrix build` в каталоге расширения (`@bitrix/cli`).

```javascript
module.exports = {
    input: './src/index.js',
    output: './dist/editor.bundle.js',
    namespace: 'BX.Vendor.Module.Editor',
    browserslist: true,
};
```

- CSS: `import './style.css';` в точке входа → `dist/editor.bundle.css`.
- Импорт: `import { Loc, Runtime } from 'main.core';`; старый CoreJS 1.0: `import 'main.date';`.
- Сборка перезаписывает `config.php` (`js`, `css`, `rel` из импортов), кроме режима `adjustConfigPhp: false`.

## config.php

```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

return [
    'js' => './dist/editor.bundle.js',
    'css' => './dist/editor.bundle.css',
    'rel' => ['main.core', 'ui.buttons'],
    'skip_core' => false,
    'settings' => ['maxItems' => 50],
];
```

Другие ключи: `lang_additional` (вычисляемые фразы), `oninit` (обработчик принимает и возвращает конфигурацию), `controllerEntrypoint` (JS-функция для `renderExtension()`, см. `bitrix-controllers`).

Настройки в JS: `Extension.getSettings('vendor.module.editor').get('maxItems', 20)` (`main.core`).

## Языковые фразы

При наличии `lang/` ядро вместе с расширением загружает `lang/<LANGUAGE_ID>/config.php` и передаёт фразы в JS: `Loc.getMessage('VENDOR_EDITOR_TITLE')`. Ключ `lang` не нужен. Фразы общие для всей страницы (`BX.message`): добавляй к кодам префикс поставщика и расширения.

```php
<?php
$MESS['VENDOR_EDITOR_TITLE'] = 'Редактор';
```

## Загрузка

```php
\Bitrix\Main\UI\Extension::load('main.core'); // BX в script.js PHP-шаблона
\Bitrix\Main\UI\Extension::load(['vendor.module.editor', 'ui.buttons']); // неизвестный ID молча пропускается
```

```javascript
import { Runtime } from 'main.core';

Runtime.loadExtension('vendor.module.editor').then(({ Editor }) => {
    new Editor().render();
});
```

`Runtime.loadExtension(...ids)` возвращает Promise с объединёнными экспортами расширений (из их `namespace`), когда JS, CSS, фразы и настройки уже на странице.

## Проверка

- [ ] Путь равен ID с заменой точек на `/`; расширения модуля имеют префикс `vendor.module.`.
- [ ] Загрузка только по ID (`Extension::load()`, `rel`, `Runtime.loadExtension()`). Нет `<script src>` / `Asset::addJs()` к файлам `/local/js/…` и `/bitrix/js/…`: обновления перемещают файлы (ui 26.675 перенёс `ui.actionpanel` в `dist/`).
- [ ] После правки `src/` пересобран `dist/`; `bundle.config.js` включён в поставку.
- [ ] Все зависимости указаны в `rel`.
- [ ] Тексты интерфейса в `lang/<lang>/config.php`, чтение через `Loc.getMessage()`.
