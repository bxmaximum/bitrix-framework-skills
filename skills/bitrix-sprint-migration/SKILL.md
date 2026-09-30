---
name: bitrix-sprint-migration
description: 'sprint.migration module — Version classes (up/down), HelperManager helpers, builders, migrate.php CLI, migrations.*.php configs. Use when writing or applying schema/content migrations, exporting iblocks/HL/options, or checking migration status.'
---

# sprint.migration

Baseline: main 23.0+ · Verified: sprint.migration 5.15.1, main 26.800.0

Marketplace/Composer module (`andreyryabin/sprint.migration`) that keeps project schema and content changes as PHP classes in VCS. Module-owned tables, events and agents of your own module go into its installer instead (`bitrix-modules`).

**Not installed?** Check `/local/modules/sprint.migration/` and `/bitrix/modules/sprint.migration/`, then `Loader::includeModule('sprint.migration')`. If it is missing, propose installing it (Marketplace or Composer). Never write a home-made migration framework.

| Task | Read |
| --- | --- |
| CLI commands, configs, version naming, console user | `rules/cli-config.md` |
| Writing `Version::up()`/`down()`, restartable batches, exchange files | `rules/writing.md` |
| Helper and builder map, method names | `rules/helpers-builders.md` |
| Safety rules, review and apply checklist | `rules/checklist.md` |

## Defaults (config `cfg`)

| Item | Default |
| --- | --- |
| Migration dir | `{local\|bitrix}/php_interface/migrations` (`local` if `local/php_interface` exists) |
| Versions table | `sprint_migration_versions` |
| Class | `namespace Sprint\Migration;` `Version<YmdHis>` extends `Version` |
| CLI | `php {module}/tools/migrate.php <command>` |

## Invariants

- Never edit the module source; write only migration files and `migrations.*.php` configs.
- Create files with `add` or a builder; hand-made names break timestamp validation.
- Identify entities by `CODE`/`XML_ID`, never by ids from another database.
- An applied migration is immutable: fix forward with a new one.
- `up`/`down` on shared or production databases only with explicit human approval.
