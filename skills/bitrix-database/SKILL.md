---
name: bitrix-database
description: 'D7 Connection, SqlHelper, SqlExpression placeholders, raw SQL, upserts, transactions, named locks, DDL builders, SqlTracker. Use when ORM is not enough: bulk SQL, reports, extra connections, schema changes, query debugging.'
---

# Direct database access

Baseline: main 23.0+ · Verified: main 26.800.0

ORM first (`bitrix-orm`: `query()`, batch writes, merge, `deleteByFilter`). Drop to SQL for upserts, windows/CTE, heavy reports, extra connections and schema changes.

| Task | Read |
| --- | --- |
| `connections` config, extra DBs, charset, after-connect hook, named locks | `rules/connections.md` |
| `query*`, `add`/`addMulti`, SqlHelper, placeholders, upsert, transactions | `rules/queries.md` |
| DDL builders, table/index changes, SqlTracker | `rules/ddl-debug.md` |

## Invariants

- Every value reaches SQL through `SqlExpression` placeholders or `SqlHelper` (`forSql`, `convertToDb*`). The `$binds` argument of `query()`/`queryExecute()` is not a prepared statement.
- Identifiers from input: whitelist first, then `quote()` / `?#`.
- Raw writes skip ORM events and cache: call `XxxTable::cleanCache()` afterwards.
- Portable SQL only: SqlHelper functions, no backticks, `ENGINE`, `UNSIGNED` (`bitrix-postgresql`).
- One connection per transaction; keep transactions short.
