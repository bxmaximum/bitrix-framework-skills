---
name: bitrix-console-commands
description: 'Bitrix CLI bitrix.php — make:* generators (module, controller, tablet, service, event, message, agent), orm:annotate, messenger:consume, own Symfony Console commands in module .settings.php. Use when scaffolding code or writing CLI/cron jobs.'
---

# Bitrix console (`bitrix.php`)

Baseline: main 23.0+ · Verified: main 26.800.0

```bash
cd /var/www/site && php bitrix/bitrix.php list        # or: cd bitrix && php bitrix.php list
php bitrix/bitrix.php help make:service
php bitrix/bitrix.php make:service Post -m vendor.blog -n   # -n: no questions
```

| Task | Read |
| --- | --- |
| Generators `make:*`, which exist on 25.x, output paths | `rules/generators.md` |
| Kernel commands: `orm:annotate`, `messenger:consume`, `update:*`, `translate:index` | `rules/generators.md` |
| Own command, registration, cron | `rules/custom-command.md` |

## Invariants

- Needs Symfony Console from Composer: `composer.json` with `symfony/console` and `.settings.php` `composer.config_path` (`bitrix-settings`); otherwise `bitrix.php` prints "Symfony Console is not installed".
- `DOCUMENT_ROOT` is derived from `getcwd()` + script path: run from the site root or `bitrix/`, never `php /abs/path/bitrix.php` from another dir.
- Commands come only from installed modules' `.settings.php` → `console.commands`; global `/local/.settings.php` is not a source.
- The kernel does `new $commandClass()` — no constructor DI; resolve services in `execute()` (`bitrix-framework`).
- Always `-n`/`--no-interaction` in cron and CI.
