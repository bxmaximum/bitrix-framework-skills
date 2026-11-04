---
name: bitrix-database
description: 'Используй для SQL вне ORM Битрикса: Connection, SqlHelper, SqlExpression, транзакции, блокировки и DDL. Сложные отчёты, дополнительные БД и отладка через SqlTracker.'
---

# Прямой доступ к базе данных

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.
В таблице выбери только нужный `rules/*.md`; пути относительны каталогу скилла.

Сначала ORM (`bitrix-orm`: `query()`, пакетные записи, merge, `deleteByFilter`). SQL нужен для upsert, оконных функций/CTE, тяжёлых отчётов, дополнительных подключений и изменения схемы.

| Задача | Читать |
| --- | --- |
| Секция `connections`, дополнительные БД, кодировка, обработчик подключения, блокировки | `rules/connections.md` |
| `query*`, `add`/`addMulti`, SqlHelper, плейсхолдеры, upsert, транзакции | `rules/queries.md` |
| DDL, изменения таблиц/индексов, SqlTracker | `rules/ddl-debug.md` |

## Обязательные правила

- Каждое значение передавай в SQL через плейсхолдеры `SqlExpression` или `SqlHelper` (`forSql`, `convertToDb*`). Аргумент `$binds` в `query()`/`queryExecute()` не является подготовленным запросом.
- Идентификаторы из ввода сначала сверяй с разрешённым списком, затем используй `quote()` / `?#`.
- Прямая запись обходит события и кеш ORM: после неё вызывай `XxxTable::cleanCache()`.
- Пиши переносимый SQL: функции SqlHelper, без обратных кавычек, `ENGINE`, `UNSIGNED` (`bitrix-postgresql`).
- Одна транзакция — одно соединение; транзакции должны быть короткими.
