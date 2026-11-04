---
name: bitrix-search
description: 'Поиск по сайту Битрикс через CSearch: индексация своих данных (CSearch::Index), запросы и search:reindex. Используй для search.page/search.title, прав поиска и выбора движка.'
---

# Модуль поиска Битрикс

Baseline: main 23.0+ · Verified: search 26.100.0, main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче. По таблице открой только нужный файл правил.

Есть во всех редакциях «Управления сайтом» и Битрикс24. Основной API индекса / поиска — `CSearch*`. **Since search 26.0** есть консольная search:reindex и служебные классы Messenger; ORM-таблета для замены CSearch нет. На старом модуле используй `CSearch::ReindexModule()` из своей команды.

| Задача | Читать |
| --- | --- |
| Индексация своей сущности, поля, права, удаление, `BeforeIndex` | `rules/indexing.md` |
| `OnReindex`, полный / модульный переиндекс, CLI, шаги | `rules/reindex.md` |
| `search.page`/`search.title`, `CSearch::Search()`, права, события URL | `rules/searching.md` |
| Sphinx/OpenSearch/MySQL/PgSQL, морфология, свой ранг, файлы сайта | `rules/engines.md` |

## Общие правила

- До `CSearch*` вызывай `Loader::includeModule('search')`. Модуль может отсутствовать / быть удалён: индексация не должна ломать запись сущности.
- Оберни `CSearch` сервисом `/local/modules/<vendor.module>/`; события сущности и `OnReindex` используют общий сборщик полей.
- Ключ индекса — (`MODULE_ID`, `ITEM_ID`); используй ID своего модуля, не `iblock` / `main`.
- Передавай `SITE_ID`, `PERMISSIONS`, `DATE_CHANGE`. Без прав запись видит только администратор; G2 — все, включая гостей.
- `search:OnReindex` регистрируй постоянно под ID модуля в установщике, не через `addEventHandler()` во время выполнения.
- Поиск — в веб-контексте: `CSearch::Search()` требует `$USER`, без него в агенте фатальная ошибка.
