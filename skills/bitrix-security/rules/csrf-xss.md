# CSRF, XSS, ссылки и перенаправления

## CSRF

- Контроллеры: стандартный фильтр `Csrf` принимает `sessid` или заголовок `X-Bitrix-Csrf-Token`. Работает только в `SCOPE_AJAX` (AJAX и контроллеры маршрутов). REST и CLI имеют свою авторизацию. Не удаляй фильтр у действий записи (`bitrix-controllers`).
- Формы: `<?= bitrix_sessid_post() ?>`. JS: `BX.bitrix_sessid()` в заголовке; `BX.ajax.runAction()` отправляет автоматически.
- Самописные обработчики (старые страницы, цели `PublicPageController`): `if (!check_bitrix_sessid()) { … }` проверяет параметр и заголовок.
- Не меняй состояние через GET.

## Экранирование и пользовательский HTML

- HTML-текст и атрибуты: `htmlspecialcharsbx($value)`. `$arResult` компонента не экранирован: делай это в шаблоне.
- Данные JS: `\Bitrix\Main\Web\Json::encode($data)`, без склейки строк. В JS вставляй текст через `BX.Text.encode()` / `Tag.safe`; `innerHTML` и `BX.Runtime.html()` вставляют исходный HTML.
- Пользовательский HTML очищай от опасных конструкций, а не экранируй целиком.

```php
$sanitizer = new \CBXSanitizer();
$sanitizer->SetLevel(\CBXSanitizer::SECURE_LEVEL_HIGH); // либо SECURE_LEVEL_MIDDLE / SECURE_LEVEL_LOW
$safeHtml = $sanitizer->SanitizeHtml($userHtml);
```

- `\Bitrix\UI\Format\BBCode\Converter::toHtml()` (ui 26.600+) сохраняет `iframe` и `style`: дополнительно фильтруй, если встраивание запрещено.

## Ссылки

`htmlspecialcharsbx()` не блокирует `javascript:` / `data:` в `href`/`src`. Разрешай схемы по списку:

```php
function safeHref(string $href): string
{
    $v = preg_replace('/[\x00-\x20]/', '', $href); // браузеры удаляют эти символы: "java\tscript:"
    if (str_starts_with(str_replace('\\', '/', $v), '//'))
    {
        return ''; // чужой хост с относительной схемой
    }
    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $v, $m) && !in_array(strtolower($m[1]), ['http', 'https'], true))
    {
        return '';
    }
    return $v;
}
```

Вывод: `htmlspecialcharsbx(safeHref($url))`. В Landing есть `\Bitrix\Landing\Sanitizer::sanitizeHrefScheme()` (landing 26.1000+).

## Перенаправления

`Engine\Response\Redirect` / `LocalRedirect()` не блокируют внешние хосты без включённой защиты модуля `security` (`skipSecurity: true` обходит её). Для ввода вроде `backurl` разрешай только локальные пути:

```php
$target = preg_replace('/[\x00-\x20]/', '', (string)$request->getQuery('backurl'));
if (!str_starts_with($target, '/') || str_starts_with(str_replace('\\', '/', $target), '//'))
{
    $target = '/';
}
```

## Приёмники исходного HTML

Передавай только экранированные или очищенные строки в:

- методы ui `Toolbar` `add…Html()` (`addBeforeTitleHtml`, `addAfterTitleHtml`, `addRightCustomHtml` и другие);
- `htmlDescription` элементов `ui.timeline`;
- `icon` группы `ui.entity-catalog` (`v-html`) и строки `slots` (компилируются в шаблон Vue).

`main.interface.toolbar` экранирует `TEXT`/`TITLE`/`LINK`/`ICON` кнопок с main 26.700: передавай обычный текст, HTML отобразится текстом.
