---
name: bitrix-postgresql
description: 'PostgreSQL in Bitrix — PgsqlConnection, MySQL→PostgreSQL wizard, module support, portable SQL and module schema. Use when configuring, migrating to or writing code for PostgreSQL (Enterprise for Postgres).'
---

# PostgreSQL

Baseline: main 23.900.900+ (first PostgreSQL-capable main) · Verified: main 26.800.0, perfmon 25.300.0

Licensed as Bitrix24 / CMS "Enterprise for Postgres". Connection class `\Bitrix\Main\DB\PgsqlConnection`; the rest of `connections` is as in `bitrix-database`.

## Before migrating

1. Current license must cover the test period (up to 6 months) and the switch. The Postgres license gives a coupon (activate after testing) and a key for a separate test copy; production stays on MySQL meanwhile.
2. `perfmon` 24.0.0+, site in UTF-8, the latest updates on the test copy.
3. Rehearse on a copy of production; going back to MySQL afterwards is manual work.
4. Close the site during the final copy.

## Wizard `perfmon.pgsql` (Settings → Wizards)

- Step 0 lists installed modules that ship `install/mysql` (or `install/db/mysql`) without the `pgsql` twin and **blocks until you uninstall them**. With the dev flag file `bitrix/modules/perfmon/dev` it only warns and removes them from `b_module` in the target DB after copying (their tables stay).
- It checks `/bitrix/modules/*` only. `/local/modules` and modules without SQL install files (`install/migrations`) are never checked: audit them yourself.
- It asks for an extra `default_pgsql` connection in `.settings.php`, copies the tables, then you switch `default`.
- Manual (CLI) migration: find unsupported modules with

```bash
for m in bitrix/modules/*/; do
  for d in install install/db; do
    [ -d "$m$d/mysql" ] && [ ! -d "$m$d/pgsql" ] && echo "no pgsql: $m$d"
  done
done
```

## Module support (docs, main 26.800)

- Not supported: abtest, advertising, b24connector, bitrix.sitecorporate, conversion, form, meeting, photogallery, report, statistic, storeassist, support, translate, webservice, wiki. `translate:index` is therefore unavailable.
- abtest and report ship pgsql scripts but are still unsupported: scripts ≠ support.
- Added recently: anonymizer 26.100, superset 26.200, note 26.250, biconnector 26.800. The Bitrix24 PG distribution has no vibecodeconnector and no workflow.
- Marketplace modules: confirm with the vendor.

## Portable code

- ORM, `SqlHelper` functions and `SqlExpression` placeholders instead of MySQL syntax: no backticks, `ENGINE=`, `UNSIGNED`, `ON DUPLICATE KEY` (use `prepareMerge*`), `GROUP_CONCAT`, `IFNULL`.
- Module schema: **Since main 26.600\*** one DB-agnostic `install/migrations/tables.php` for both DBMS (`bitrix-modules`); older kernels need paired `install/db/mysql/` and `install/db/pgsql/` scripts.
- Columns: `datetime`, not `timestamp`. Index names are schema-wide in PostgreSQL: prefix them with the table name. The migration layer normalizes and hashes long names (main 26.800).
- `getInsertedId()` after a multi-row insert is the last ID (MySQL: the first).
- Server: `standard_conforming_strings = on` (the site checker tests it since main 26.800).

## Checklist

- [ ] Every installed module is supported or uninstalled before the wizard.
- [ ] `/local/modules` and marketplace modules audited for raw MySQL SQL and install scripts.
- [ ] Custom tables described via `install/migrations/tables.php` (26.600+) or paired SQL files.
- [ ] Migration rehearsed on a copy; coupon activated only after testing.
