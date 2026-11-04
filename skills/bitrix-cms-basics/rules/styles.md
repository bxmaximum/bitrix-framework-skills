# Размещение CSS

| Владелец разметки | Где хранить CSS |
| --- | --- |
| Обрамление сайта (`header.php`/`footer.php`, сетка) | `template_styles.css` шаблона сайта |
| Содержимое страницы, оформляемое визуальным редактором | `styles.css` + `.styles.php` шаблона сайта |
| Шаблон компонента (`template.php`) | `style.css` рядом с ним |
| Интерфейс JS-расширения | `css` расширения (`bitrix-extensions`) |
| Отдельная страница | `Asset::getInstance()->addCss()` |

- Визуальный редактор вставляет `styles.css` в свой iframe: стили обрамления здесь искажают редактор. Оба файла редактируются в Настройки → Сайты → Шаблоны сайтов.
- `.styles.php` возвращает список стилей редактора; сами правила CSS хранятся в `styles.css`:

```php
return [
    'note' => [                          // ключ — класс CSS
        'tag' => 'p',                    // теги через запятую
        'title' => 'Примечание',         // название в редакторе
        'html' => '<span class="note">Образец</span>', // необязательный образец
        // необязательный section группирует записи
    ],
];
```

- CSS только для редактора: `'EDITOR_STYLES' => ['/local/css/editor.css']` в `description.php` (`$arTemplate`); на публичном сайте не загружается.

## Подключение

```php
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . '/css/page.css');
// true вторым аргументом: набор шаблона после styles.css / template_styles.css
\Bitrix\Main\UI\Extension::load('vendor.module.card');   // JS + CSS расширения
```

Классический шаблон: `$APPLICATION->ShowCSS()` в `<head>` файла `header.php` выводит собранные стили. `$APPLICATION->SetAdditionalCSS($path)` добавляет стиль; для нового кода используй `Asset::addCss()`.

## Оптимизация

Опции main: объединение CSS (`optimize_css_files`), файлы `.min` (`use_minified_assets`), gzip-копии (`compres_css_js_files`, нужен zlib). Объединяется только CSS из Asset; в административной части и AJAX объединения нет. Отключение на странице: `Asset::getInstance()->disableOptimizeCss()`. Результат: `/bitrix/cache/css/<SITE_ID>/<template>/`. Если вид остался прежним, очисти кеш после правки CSS.
