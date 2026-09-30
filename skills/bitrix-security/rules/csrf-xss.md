# CSRF, XSS, links, redirects

## CSRF

- Controllers: the default `Csrf` prefilter accepts the `sessid` param or the `X-Bitrix-Csrf-Token` header. It runs only in `SCOPE_AJAX` (AJAX and routed controllers). REST and CLI rely on their own auth. Never drop it on actions that change data (`bitrix-controllers`).
- Forms: `<?= bitrix_sessid_post() ?>`. JS: `BX.bitrix_sessid()` in the header; `BX.ajax.runAction()` sends it itself.
- Hand-written handlers (legacy pages, `PublicPageController` targets): `if (!check_bitrix_sessid()) { … }` checks the param and the header.
- No state changes on GET.

## Escaping and user HTML

- HTML text and attributes: `htmlspecialcharsbx($value)`. Component `$arResult` is not escaped: escape in the template.
- Data for JS: `\Bitrix\Main\Web\Json::encode($data)`, never string concatenation. In JS insert text via `BX.Text.encode()` / `Tag.safe`; `innerHTML` and `BX.Runtime.html()` insert raw HTML.
- User HTML: sanitize, don't escape.

```php
$sanitizer = new \CBXSanitizer();
$sanitizer->SetLevel(\CBXSanitizer::SECURE_LEVEL_HIGH); // or SECURE_LEVEL_MIDDLE / SECURE_LEVEL_LOW
$safeHtml = $sanitizer->SanitizeHtml($userHtml);
```

- `\Bitrix\UI\Format\BBCode\Converter::toHtml()` (ui 26.600+) keeps `iframe` and `style`: post-filter where embeds are not allowed.

## Links

`htmlspecialcharsbx()` does not stop `javascript:` or `data:` in `href`/`src`. Allow-list the scheme:

```php
function safeHref(string $href): string
{
    $v = preg_replace('/[\x00-\x20]/', '', $href); // browsers drop these: "java\tscript:"
    if (str_starts_with(str_replace('\\', '/', $v), '//'))
    {
        return ''; // protocol-relative foreign host
    }
    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $v, $m) && !in_array(strtolower($m[1]), ['http', 'https'], true))
    {
        return '';
    }
    return $v;
}
```

Output: `htmlspecialcharsbx(safeHref($url))`. Landing code has `\Bitrix\Landing\Sanitizer::sanitizeHrefScheme()` (landing 26.1000+).

## Redirects

`Engine\Response\Redirect` / `LocalRedirect()` don't block external hosts unless the `security` module's redirect protection is on (`skipSecurity: true` bypasses it). For `backurl`-style input accept only local paths:

```php
$target = preg_replace('/[\x00-\x20]/', '', (string)$request->getQuery('backurl'));
if (!str_starts_with($target, '/') || str_starts_with(str_replace('\\', '/', $target), '//'))
{
    $target = '/';
}
```

## Raw-HTML sinks

Pass only escaped or sanitized strings to:

- ui `Toolbar` `add…Html()` methods (`addBeforeTitleHtml`, `addAfterTitleHtml`, `addRightCustomHtml`, …);
- `ui.timeline` item `htmlDescription`;
- `ui.entity-catalog` group `icon` (`v-html`) and `slots` strings (compiled into the Vue template).

`main.interface.toolbar` escapes button `TEXT`/`TITLE`/`LINK`/`ICON` since main 26.700: pass plain text there, HTML will show as text.
