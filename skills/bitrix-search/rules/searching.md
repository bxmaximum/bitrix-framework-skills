# Поиск

## Сначала компоненты

| Компонент | Назначение |
| --- | --- |
| `bitrix:search.page` | Результаты, страницы, сортировка, теги; несовместим с композитом (`setFrameMode(false)`) |
| `bitrix:search.title` | Поиск заголовка по мере ввода в шапке: `PAGE`, `NUM_CATEGORIES`, `CATEGORY_<n>`, `CATEGORY_<n>_TITLE`, `TOP_COUNT`, `SHOW_OTHERS`, `CHECK_DATES` |
| `bitrix:search.form` | Форма отправляет q на `PAGE` (`#SITE_DIR#search/index.php`) |
| `bitrix:search.tags.cloud`, `search.tags.input`, `search.suggest.input` | Теги и подсказки |

Ограничение своими записями: `arrFILTER => ['vendor.docs']` у `search.page`, `CATEGORY_0 => ['vendor.docs']` у `search.title`; любой незарезервированный код становится `=MODULE_ID`. Допусловия: `FILTER_NAME` — глобальный массив ключей фильтра `CSearch`, например `['PARAM1' => 'contract']`. Параметры: `PAGE_RESULT_COUNT`, `DEFAULT_SORT` (`rank`|`date`), `CHECK_DATES`, `USE_TITLE_RANK`, `RESTART`, `NO_WORD_LOGIC`, `USE_LANGUAGE_GUESS`. Свой вывод — копия шаблона в `/local/templates/<site>/components/bitrix/search.page/` (`bitrix-components`).

## Из кода

```php
$search = new CSearch();
$search->Search([
    'QUERY' => $query,
    'SITE_ID' => SITE_ID,
    'MODULE_ID' => 'vendor.docs',
    'PARAM1' => 'contract',
    'CHECK_DATES' => 'Y',
], ['RANK' => 'DESC', 'DATE_CHANGE' => 'DESC']);

if ((int)$search->errorno !== 0)
{
    return $result->addError(new \Bitrix\Main\Error((string)$search->error));   // $result: \Bitrix\Main\Result
}
$search->NavStart(20, false);
while ($row = $search->GetNext())
{
    // TITLE_FORMATED, BODY_FORMATED — HTML с выделением; URL, ITEM_ID, PARAM2 в $row
}
```

Сигнатура: `Search($arParams, $aSort = [], $aParamsEx = [], $bTagsCloud = false)`.

- Фильтр: `QUERY`, `TAGS` (через запятую), `SITE_ID`, `MODULE_ID`, `ITEM_ID`, `PARAM1`, `PARAM2` (строка / массив), `PARAMS => ['name' => value]`, `URL`, `CHECK_DATES`, `>=DATE_CHANGE` / `<=DATE_CHANGE` (формат сайта), вложенный `['LOGIC' => 'OR', …]`.
- `$aParamsEx`: числовые элементы — OR-группы тех же ключей; `'STEMMING' => false` отключает морфологию запроса.
- Сортировка: `RANK`, `TITLE_RANK`, `CUSTOM_RANK`, `DATE_CHANGE`, `TITLE`, `ID`, `PARAM1`, `PARAM2`, …; стандарт — `CUSTOM_RANK`, `RANK`, `DATE_CHANGE` по убыванию.
- `errorno`: 1 — скобки, 2 — положение оператора, 3 — пустой запрос, 4 — слишком длинный. Проверь до `NavStart()`.
- Число результатов ограничено `max_result_size` (500); фрагменты читают до `max_body_size`.
- `NavStart()` также записывает статистику фраз.

Только заголовки: `(new CSearchTitle())->Search($phrase, $nTopCount = 5, $arParams = [], $bNotFilter = false, $order = '')` → bool, затем строки `Fetch()` с выделением в NAME. Требует константы `SITE_ID`/`LANGUAGE_ID`, только публичный контекст.

## Права

Применяются всегда, отключения нет: администратор видит всё; гость — G2; авторизованный — AU, U<id>, G<group> и коды обработчиков `search:OnSearchCheckPermissions` (socialnetwork, intranet, crm добавляют свои). Индексируй с кодами, которые получит ищущий.

- `CSearch::Search()` требует `$USER`: без него в агенте фатальная ошибка. Веб-контекст либо предварительная инициализация пользователя. Индексация из агента работает.

## События вывода

- `OnSearchGetURL` (`$row`): только URL с `=`; верни адрес, для чужого модуля `null`.
- `OnSearch` (`$query`): возвращённая строка дописывается к каждому URL результата.
- `OnSearchPrepareFilter` (`$alias, $field, $value`): SQL для неизвестного ключа фильтра; первый непустой побеждает. Экранируй значения.
