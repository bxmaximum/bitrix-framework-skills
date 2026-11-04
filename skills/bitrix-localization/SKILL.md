---
name: bitrix-localization
description: 'Используй для локализации Битрикса: Loc::getMessage, lang-файлы, подмена перевода и диагностика пропусков. Форматы культуры, BX.message и CUtil::translit.'
---

# Локализация

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

## Языковые файлы

```
/local/modules/vendor.module/lib/Service/PostService.php
/local/modules/vendor.module/lib/Service/lang/ru/PostService.php     # ближайший вверх по дереву lang/ с тем же относительным именем
/local/modules/vendor.module/lib/Service/lang/en/PostService.php
```

```php
<?php
$MESS['VENDOR_MODULE_POST_PUBLISHED'] = 'Запись #NAME# опубликована';
```

- UTF-8 без BOM. **Since main 26.800** ядро не преобразует кодировки lang-файлов, даже из `translation_repository`: другая кодировка даст искажённый текст.
- Ключи `<VENDOR>_<MODULE>_<CONTEXT>_<CODE>`; без префикса возможны конфликты с другими модулями.
- Отсутствующая фраза берётся из файла `Loc::getDefaultLang($lang)`: `ua`, `de` → `en`; `kz`, `by`, `uz` → `ru`; другие языки — по карте `default_language` в `.settings.php` (`['value' => ['fr' => 'en']]`), иначе `en`. Поставляй как минимум `en` и `ru`.

## `Loc::getMessage`

```php
use Bitrix\Main\Localization\Loc;

echo Loc::getMessage('VENDOR_MODULE_POST_PUBLISHED', ['#NAME#' => $title]);
echo Loc::getMessage('VENDOR_MODULE_POST_PUBLISHED', null, 'en');
Loc::loadLanguageFile($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/mail.php');   // немедленная загрузка фраз другого файла
```

- `getMessage()` при первом вызове загружает lang-файл **вызывающего файла**; `Loc::loadMessages(__FILE__)` лишь регистрирует дополнительные файлы для отложенного поиска. Вызывай его прямо из файла с соответствующим `lang/`: обёртка заставит ядро искать рядом с обёрткой.
- Поиск фразы только среди других зарегистрированных файлов требует лишних подключений. `define('BX_MESS_LOG', '/abs/path/mess.log')` в `init.php` журналирует пропуски (`[ru]CODE: not found for /file.php`) и такие поиски (`CTranslateUtils::CopyMessage(...)`); перенеси или скопируй фразу.
- Компоненты, шаблоны и административные страницы автоматически загружают соседние lang-файлы.

## Подмена фраз ядра

`/local/php_interface/user_lang/<lang>/lang.php`; ключ — путь языкового файла от корня сайта:

```php
<?php
$MESS['/bitrix/components/bitrix/news.list/templates/.default/lang/ru/template.php']['CT_BNL_ELEMENT_DELETE_CONFIRM'] = 'Удалить?';
```

## Диагностика (**Since main 26.800**)

```php
if (\Bitrix\Main\Config\Configuration::getValue('exception_handling')['debug'] ?? false)
{
    Loc::setMessageDecorator(static fn ($code, $raw, $s, $sourceFile, $language) => $s ?? "[$code]");
}
$file = Loc::getMessageSourceFile('VENDOR_MODULE_POST_PUBLISHED');   // файлы, загруженные после установки декоратора
```

Декоратор вызывается при каждом `getMessage()` до замены `#REPLACE#`; устанавливай рано (`init.php`) и только при разработке. С `BX_MESS_CODE` фразы заменяются кодами при загрузке (26.800+), поэтому ни разу не загруженные возвращают `null`.

## Форматы культуры

```php
$culture = \Bitrix\Main\Context::getCurrent()->getCulture();
$culture->getDateFormat();             // маска сайта 'DD.MM.YYYY', а не формат PHP
$culture->getShortTimeFormat();        // формат PHP ('H:i', 'g:i a')
$culture->getNameFormat();             // '#NAME# #LAST_NAME#'
$culture->getNumberDecimals();
$culture->getNumberDecimalSeparator();
$culture->getNumberThousandsSeparator();

echo $dateTime->toString($culture);    // дата/время культуры в часовом поясе пользователя
echo \FormatDate('SHORT', $dateTime);  // 'SHORT' | 'FULL' | формат PHP с локализованными месяцами ('j F Y')
```

Не передавай `getDateFormat()` / `getDateTimeFormat()` в `format()`: сначала преобразуй маски (`bitrix-datetime`).

## JavaScript

```php
\Bitrix\Main\Page\Asset::getInstance()->addString(
    '<script>BX.message(' . \Bitrix\Main\Web\Json::encode([
        'VENDOR_POST_SAVE' => Loc::getMessage('VENDOR_POST_SAVE'),
    ]) . ');</script>'
);
```

- Расширения автоматически публикуют фразы `lang/<lang>/config.php`; `lang_additional` в `config.php` должен иметь вид `code => phrase` (`bitrix-extensions`).
- ES-модули: `import { Loc } from 'main.core'; Loc.getMessage('VENDOR_POST_SAVE', { '#N#': 5 })`. BitrixVue: `$Bitrix.Loc.getMessage()` (`bitrix-vue`).

## Транслитерация

```php
$code = rtrim(\CUtil::translit($name, 'ru', ['replace_space' => '-', 'replace_other' => '-']), '-');
```

**Since main 26.800** конечный непереводимый символ сохраняется (`'Скидка 10%'` → `skidka_10_`); обрезай свой `replace_other`.

## Инструменты

`php bitrix/bitrix.php translate:index` (нужен `translate`, недоступен в PostgreSQL) индексирует lang-файлы для «Настройки → Локализация».
