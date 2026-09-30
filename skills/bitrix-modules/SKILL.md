---
name: bitrix-modules
description: 'Custom Bitrix modules in /local/modules — CModule installer (DoInstall/DoUninstall), version.php, make:module, install/migrations tables/events/agents, options page, autoload. Use when creating, installing or uninstalling a module.'
---

# Bitrix modules

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| New module: id, skeleton, `install/index.php`, version, lang, uninstall | `rules/installer.md` |
| Tables, events, agents in `install/migrations/*.php` (main 26.600+) | `rules/install-migrations.md` |
| Options page, `default_option.php`, PSR-4 autoload, module routes | `rules/options-autoload.md` |

## Invariants

- Module dir `/local/modules/<vendor>.<module>/`; id lowercase `vendor.module`; installer class `vendor_module`; namespace `Vendor\Module` (one-word id `mymodule` → `Bitrix\Mymodule`).
- Nothing works until the module is registered in `b_module`: `Loader::includeModule()` returns `false`, and module `.settings.php` (`services`, `controllers`, `console`, `messenger`) is not read.
- Install/uninstall are symmetric: every table, handler, agent and option you create has a removal path; re-running install must not fail.
- Event registration uses kernel parameter names (`fromModuleId:`) or positional args; handler signature depends on the dispatcher — `bitrix-events`.
- Module routes are `require`d from `/local/routes/web.php`; a `routing` section in module `.settings.php` is ignored (`bitrix-routing`).
- Project data/content migrations (iblocks, options per environment) belong to `bitrix-sprint-migration`, not to the installer.
