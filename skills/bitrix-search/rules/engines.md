# Движки, морфология, ранжирование

## Полнотекстовый движок

Опция `search` / `full_text_engine` страницы настроек, читается `CSearchFullText::getInstance()`:

| Значение | Движок | Особенности |
| --- | --- | --- |
| `bitrix` (по умолчанию) | `CSearchStemTable` | Таблицы основ слов в основной БД |
| `mysql` | `CSearchMysql` | MySQL FULLTEXT; доступен только на MySQL |
| `pgsql` | `CSearchPgsql` | GIN `to_tsvector`; доступен только на PostgreSQL |
| `sphinx` | `CSearchSphinx` | SphinxQL; `sphinx_connection` (`127.0.0.1:9306`), `sphinx_index_name`. Sphinx 3.x — **Since search 25.200** |
| `opensearch` | `CSearchOpenSearch` | `opensearch_connection`, `opensearch_user`, `opensearch_index`; пароль в `CPasswordStorage` (`search@opensearch_password`); индекс на сайт |

- Движка Elasticsearch в ядре нет.
- **Since search 26.100** `getInstance()` создаёт объект без подключения; ConnectionException может возникнуть при первом запросе. Адрес и индекс задаются в конструкторе движка, connect($ignoreErrors = false) принимает только режим ошибки. На старом модуле проверяй прежнюю сигнатуру connect(). Индексацию защищай на уровне сервиса, сбой поиска не должен отменять запись сущности.
- Смена движка требует полного переиндекса (`rules/reindex.md`).
- Пользовательский ранг `CSearchCustomRank` не передаётся в Sphinx/OpenSearch.

## Морфология и слова

- `use_stemming` по умолчанию Y; на запрос — `$aParamsEx['STEMMING'] = false`. Встроенные языки: ru, en, tr.
- Для своего языка определи `stemming_<lang>($word, $flags = 0)` до поиска, например в `/local/php_interface/init.php`. Ядро также читает `/bitrix/php_interface/<lang>/search/stemming.php`, но аналогичный `/local` не ищет; предпочитай регистрацию через init.php.
- `letters` по умолчанию `-` добавляет символы слова; изменение требует полного переиндекса.
- `agent_stemming = Y` переносит морфологию в агент `CSearchStemTable::DelayedStemIndex()`: новые записи находятся после его запуска.

## Ранжирование и теги

- Порядок: `CUSTOM_RANK`, релевантность `RANK`, дата.
- Ранг записи: `CUSTOM_RANK` в `CSearch::Index()`, но совпавшее правило `b_search_custom_rank` перекрывает его.
- Правила: `(new CSearchCustomRank())->Add(['SITE_ID' => 's1', 'MODULE_ID' => 'vendor.docs', 'PARAM1' => 'contract', 'RANK' => 10])`; для применения к старым записям — `StartUpdate()`, затем `NextUpdate()` до завершения. Либо редактирование в административном интерфейсе.
- Теги: `TAGS` в `Index()`; список `CSearchTags::GetList($arSelect = [], $arFilter = [], $arOrder = [], $limit = 100)` с проверкой прав; облако `bitrix:search.tags.cloud`.
- Подсказки `b_search_suggest` строятся из запросов без фильтра прав. Не включай `USE_SUGGEST`, если фразы конфиденциальны.

## Файлы сайта (модуль `main`)

Публичные файлы индексируются как `MODULE_ID = 'main'` при полном переиндексе: `include_mask` (`*.php;*.html;*.htm`), `exclude_mask` (по умолчанию исключает `/bitrix/*`, `/upload/*`, `/local/*`). Один файл: `CSearch::ReindexFile([$siteId, '/about/index.php'])`. Содержимое других форматов дают `OnSearchGetFileContent`.

## Обслуживание

- Ежедневные агенты: `CSearchSuggest::CleanUpAgent()`, `CSearchStatistic::CleanUpAgent()`. Агента переиндекса нет.
- Статистика `stat_phrase` растёт с каждым поиском; ограничь `stat_phrase_save_days`.

**Since search 26.100** временная опция full_text_delay_index = Y откладывает запись OpenSearch в очередь search_sync_content. По умолчанию N; штатный переиндекс отключает задержку в своём контексте. Это не автоматическое переключение на другой поисковый движок. На старой версии сохраняй задание на повторную индексацию своим фоновым обработчиком (`bitrix-background-jobs`).
