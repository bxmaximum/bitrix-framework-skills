# Searching

## Components first

| Component | Use |
| --- | --- |
| `bitrix:search.page` | Results page with paging, sort, tags. Not composite-compatible (`setFrameMode(false)`) |
| `bitrix:search.title` | As-you-type title search in the header (`PAGE`, `NUM_CATEGORIES`, `CATEGORY_<n>`, `CATEGORY_<n>_TITLE`, `TOP_COUNT`, `SHOW_OTHERS`, `CHECK_DATES`) |
| `bitrix:search.form` | Plain form posting `q` to `PAGE` (`#SITE_DIR#search/index.php`) |
| `bitrix:search.tags.cloud`, `search.tags.input`, `search.suggest.input` | Tags and suggestions |

Restrict `search.page` / `search.title` to your items: `arrFILTER => ['vendor.docs']` (`CATEGORY_0 => ['vendor.docs']` for the title) — any non-reserved code becomes `=MODULE_ID`. Extra conditions: `FILTER_NAME` = name of a global array with `CSearch` filter keys (e.g. `['PARAM1' => 'contract']`). Main params: `PAGE_RESULT_COUNT`, `DEFAULT_SORT` (`rank`|`date`), `CHECK_DATES`, `USE_TITLE_RANK`, `RESTART`, `NO_WORD_LOGIC`, `USE_LANGUAGE_GUESS`. Customize output by copying the template to `/local/templates/<site>/components/bitrix/search.page/` (`bitrix-components`).

## From code

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
    // $row['TITLE_FORMATED'], $row['BODY_FORMATED'] (HTML with highlights), $row['URL'], $row['ITEM_ID'], $row['PARAM2']
}
```

Signature: `Search($arParams, $aSort = [], $aParamsEx = [], $bTagsCloud = false)`.

- Filter keys: `QUERY`, `TAGS` (comma list), `SITE_ID`, `MODULE_ID`, `ITEM_ID`, `PARAM1`, `PARAM2` (string or array), `PARAMS => ['name' => value]`, `URL`, `CHECK_DATES`, `>=DATE_CHANGE` / `<=DATE_CHANGE` (site format), nested `['LOGIC' => 'OR', …]`.
- `$aParamsEx`: numeric entries are OR-groups of the same keys; `'STEMMING' => false` disables morphology for this query.
- Sort keys: `RANK`, `TITLE_RANK`, `CUSTOM_RANK`, `DATE_CHANGE`, `TITLE`, `ID`, `PARAM1`, `PARAM2`, …; default `CUSTOM_RANK`, `RANK`, `DATE_CHANGE` desc.
- `errorno`: 1 unbalanced bracket, 2 misplaced operator, 3 empty query, 4 query too long. Check it before `NavStart()`.
- Results are capped by option `max_result_size` (default 500); snippets read up to `max_body_size`.
- `NavStart()` also records phrase statistics.

Title-only: `(new CSearchTitle())->Search($phrase, $nTopCount = 5, $arParams = [], $bNotFilter = false, $order = '')` returns `bool`, then `Fetch()` rows (`NAME` highlighted). Uses the `SITE_ID`/`LANGUAGE_ID` constants — public context only.

## Permissions

Always applied, no switch: admins see everything; anonymous users match only `G2`; authorized users match `AU`, `U<id>`, `G<group>` plus codes returned by `search:OnSearchCheckPermissions` handlers (socialnetwork, intranet and crm add theirs). Index with codes the searcher will actually have.

- `CSearch::Search()` needs a `$USER` object: it fatals in agents (no `$USER`). Search from web context or init a user first; indexing from agents works.

## Hooks on output

- `OnSearchGetURL` (`$row`) — only for items whose `URL` starts with `=`; return the real URL, or `null` for other modules' rows.
- `OnSearch` (`$query`) — returned string is appended to every result URL.
- `OnSearchPrepareFilter` (`$alias, $field, $value`) — SQL for unknown filter keys; first non-empty wins. Escape values.
