# CSS placement

| Owner of the markup | CSS lives in |
| --- | --- |
| Site frame (`header.php`/`footer.php`, grid) | `template_styles.css` of the site template |
| Page content styleable in the visual editor | `styles.css` + `.styles.php` of the site template |
| Component template (`template.php`) | `style.css` next to it |
| JS extension UI | the extension's `css` (`bitrix-extensions`) |
| One-off page | `Asset::getInstance()->addCss()` |

- The visual editor injects `styles.css` into its iframe: never put frame rules there, they distort the editor. Both files are editable in Admin → Settings → Sites → Site templates.
- `.styles.php` returns the editor's style list (the CSS rule itself goes to `styles.css`):

```php
return [
    'note' => [                          // key = CSS class
        'tag' => 'p',                    // tag(s), comma-separated
        'title' => 'Note',               // name in the editor
        'html' => '<span class="note">Preview</span>', // optional preview
        // optional 'section' groups entries
    ],
];
```

- Editor-only CSS: `'EDITOR_STYLES' => ['/local/css/editor.css']` in `description.php` (`$arTemplate`); not loaded on the public site.

## Include APIs

```php
\Bitrix\Main\Page\Asset::getInstance()->addCss(SITE_TEMPLATE_PATH . '/css/page.css');
// 2nd arg true → template set, after styles.css / template_styles.css
\Bitrix\Main\UI\Extension::load('vendor.module.card');   // extension JS + CSS
```

Classic templates: `$APPLICATION->ShowCSS()` in `<head>` of `header.php` outputs the collected set; `$APPLICATION->SetAdditionalCSS($path)` adds to it (compat; prefer `Asset::addCss()`).

## Optimization

Main module options: merge CSS (`optimize_css_files`), use `.min` files (`use_minified_assets`), gzip copies (`compres_css_js_files`, needs zlib). Merging covers Asset-registered CSS only, is skipped in the admin section and AJAX, and can be turned off per page with `Asset::getInstance()->disableOptimizeCss()`. Merged files: `/bitrix/cache/css/<SITE_ID>/<template>/` — clear the cache after editing CSS if the old look persists.
