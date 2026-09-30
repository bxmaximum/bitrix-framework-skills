---
name: bitrix-localization
description: 'Bitrix localization — Loc::getMessage, lang files, fallback languages, phrase overrides, Culture formats, BX.message, CUtil::translit, message diagnostics. Use when adding or translating phrases or debugging missing messages.'
---

# Localization

Baseline: main 23.0+ · Verified: main 26.800.0

## Lang files

```
/local/modules/vendor.module/lib/Service/PostService.php
/local/modules/vendor.module/lib/Service/lang/ru/PostService.php     # nearest lang/ dir up the tree, same relative name
/local/modules/vendor.module/lib/Service/lang/en/PostService.php
```

```php
<?php
$MESS['VENDOR_MODULE_POST_PUBLISHED'] = 'Post #NAME# published';
```

- UTF-8 without BOM. **Since main 26.800** the kernel no longer converts lang-file encodings (not even from `translation_repository`): non-UTF-8 files show mojibake.
- Keys `<VENDOR>_<MODULE>_<CONTEXT>_<CODE>`; unprefixed keys collide with other modules.
- A missing phrase falls back to the file of `Loc::getDefaultLang($lang)`: `ua`, `de` → `en`; `kz`, `by`, `uz` → `ru`; other languages → the `.settings.php` `default_language` map (`['value' => ['fr' => 'en']]`), else `en`. Ship at least `en` (and `ru`).

## `Loc::getMessage`

```php
use Bitrix\Main\Localization\Loc;

echo Loc::getMessage('VENDOR_MODULE_POST_PUBLISHED', ['#NAME#' => $title]);
echo Loc::getMessage('VENDOR_MODULE_POST_PUBLISHED', null, 'en');
Loc::loadLanguageFile($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/mail.php');   // phrases of another file, now
```

- `getMessage()` loads the lang file of the **calling file** on first use; `Loc::loadMessages(__FILE__)` only registers extra files for that lazy search. Call it directly in the file whose `lang/` holds the phrase: a wrapper helper makes the kernel look next to the helper.
- A phrase found only by scanning other registered files costs extra includes. `define('BX_MESS_LOG', '/abs/path/mess.log')` in `init.php` logs misses (`[ru]CODE: not found for /file.php`) and such lookups (`CTranslateUtils::CopyMessage(...)`); fix by moving or copying the phrase.
- Components, templates and admin pages load their neighbouring lang files automatically.

## Overriding kernel phrases

`/local/php_interface/user_lang/<lang>/lang.php`, keyed by the lang file path from the document root:

```php
<?php
$MESS['/bitrix/components/bitrix/news.list/templates/.default/lang/ru/template.php']['CT_BNL_ELEMENT_DELETE_CONFIRM'] = 'Удалить?';
```

## Diagnostics (**Since main 26.800**)

```php
if (\Bitrix\Main\Config\Configuration::getValue('exception_handling')['debug'] ?? false)
{
    Loc::setMessageDecorator(static fn ($code, $raw, $s, $sourceFile, $language) => $s ?? "[$code]");
}
$file = Loc::getMessageSourceFile('VENDOR_MODULE_POST_PUBLISHED');   // files loaded after the decorator was set
```

The decorator runs on every `getMessage()` before `#REPLACE#` substitution; set it early (`init.php`), dev only. With `BX_MESS_CODE` defined, phrases are replaced by their codes at load time (26.800+), so never-loaded phrases return `null`.

## Culture formats

```php
$culture = \Bitrix\Main\Context::getCurrent()->getCulture();
$culture->getDateFormat();             // site mask 'DD.MM.YYYY', not a PHP format
$culture->getShortTimeFormat();        // PHP format ('H:i', 'g:i a')
$culture->getNameFormat();             // '#NAME# #LAST_NAME#'
$culture->getNumberDecimals();
$culture->getNumberDecimalSeparator();
$culture->getNumberThousandsSeparator();

echo $dateTime->toString($culture);    // culture date-time, user TZ
echo \FormatDate('SHORT', $dateTime);  // 'SHORT' | 'FULL' | PHP format with localized month names ('j F Y')
```

Never pass `getDateFormat()` / `getDateTimeFormat()` to `format()`: convert masks first (`bitrix-datetime`).

## JavaScript

```php
\Bitrix\Main\Page\Asset::getInstance()->addString(
    '<script>BX.message(' . \Bitrix\Main\Web\Json::encode([
        'VENDOR_POST_SAVE' => Loc::getMessage('VENDOR_POST_SAVE'),
    ]) . ');</script>'
);
```

- Extensions: phrases in the extension's `lang/<lang>/config.php` are published automatically; `lang_additional` in `config.php` must be `code => phrase` (`bitrix-extensions`).
- ES modules: `import { Loc } from 'main.core'; Loc.getMessage('VENDOR_POST_SAVE', { '#N#': 5 })`. BitrixVue: `$Bitrix.Loc.getMessage()` (`bitrix-vue`).

## Transliteration

```php
$code = rtrim(\CUtil::translit($name, 'ru', ['replace_space' => '-', 'replace_other' => '-']), '-');
```

**Since main 26.800** a trailing untranslatable char is kept (`'Скидка 10%'` → `skidka_10_`); trim with your `replace_other`.

## Tools

`php bitrix/bitrix.php translate:index` (module `translate`, not available on PostgreSQL) indexes lang files for Settings → Localization.
