# Engines, morphology, ranking

## Full-text engine

Option `search` / `full_text_engine` (module settings page), read by `CSearchFullText::getInstance()`:

| Value | Engine | Notes |
| --- | --- | --- |
| `bitrix` (default) | `CSearchStemTable` | Stem tables in the main DB |
| `mysql` | `CSearchMysql` | MySQL FULLTEXT index; offered only on MySQL |
| `pgsql` | `CSearchPgsql` | GIN `to_tsvector` index; offered only on PostgreSQL |
| `sphinx` | `CSearchSphinx` | SphinxQL; options `sphinx_connection` (`127.0.0.1:9306`), `sphinx_index_name`. Sphinx 3.x supported since search 25.200 |
| `opensearch` | `CSearchOpenSearch` | options `opensearch_connection`, `opensearch_user`, `opensearch_index`; password in `CPasswordStorage` (`search@opensearch_password`); one index per site |

- No Elasticsearch engine in the kernel.
- If Sphinx/OpenSearch is unreachable, `getInstance()` throws `Bitrix\Main\DB\ConnectionException` — indexing calls fail too. Wrap `CSearch::Index()` in your service with a try/catch and log.
- Changing the engine requires a full reindex (`rules/reindex.md`).
- Custom ranking (`CSearchCustomRank`) is not pushed to Sphinx/OpenSearch.

## Morphology and words

- Option `use_stemming` (default `Y`); per query `$aParamsEx['STEMMING'] = false`. Bundled stemmers: `ru`, `en`, `tr`.
- Custom stemmer for a language: define `stemming_<lang>($word, $flags = 0)` before search runs (e.g. from `/local/php_interface/init.php`), or put it in `/bitrix/php_interface/<lang>/search/stemming.php` — `/local` is not scanned for that file.
- Option `letters` (default `-`) adds characters that belong to words; change → full reindex.
- Option `agent_stemming = Y` moves stemming to an agent (`CSearchStemTable::DelayedStemIndex()`); new items are findable only after it runs.

## Ranking and tags

- Order: `CUSTOM_RANK`, then relevance `RANK`, then date.
- Per-item boost: `CUSTOM_RANK` in `CSearch::Index()`, but a matching rule from `b_search_custom_rank` overrides it.
- Rules: `(new CSearchCustomRank())->Add(['SITE_ID' => 's1', 'MODULE_ID' => 'vendor.docs', 'PARAM1' => 'contract', 'RANK' => 10])`, then call `StartUpdate()` and `NextUpdate()` until done to apply to existing items (or edit rules in admin).
- Tags: `TAGS` field in `Index()`; list with `CSearchTags::GetList($arSelect = [], $arFilter = [], $arOrder = [], $limit = 100)` (rights-checked); cloud = `bitrix:search.tags.cloud`.
- Suggestions (`b_search_suggest`) are built from queries and are not filtered by rights — don't enable `USE_SUGGEST` where phrases are sensitive.

## Static files (module `main`)

Public files are indexed as `MODULE_ID = 'main'` during full reindex, by options `include_mask` (`*.php;*.html;*.htm`) and `exclude_mask` (default excludes `/bitrix/*`, `/upload/*`, `/local/*`). One file: `CSearch::ReindexFile([$siteId, '/about/index.php'])`. File contents of other types come from `OnSearchGetFileContent` handlers.

## Maintenance

- Daily agents: `CSearchSuggest::CleanUpAgent()`, `CSearchStatistic::CleanUpAgent()`. No reindex agent.
- Phrase statistics (`stat_phrase`) grow with every search; keep `stat_phrase_save_days` reasonable.
