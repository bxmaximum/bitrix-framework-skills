---
name: bitrix-project-structure
description: 'Where code lives in a Bitrix project — /local vs /bitrix, php_interface files, Loader includeModule/requireModule, PSR-4 autoload of module lib/, Composer, file priority. Use when deciding where to put code or why a class does not load.'
---

# Project structure and autoloading

Baseline: main 23.0+ · Verified: main 26.800.0

## Roots

- `/bitrix/` — kernel and marketplace modules; replaced by updates. Never edit (`bitrix-framework`).
- `/local/` — all project code; same relative path in `/local/` wins over `/bitrix/`.
- `/upload/` — user/module files.

```
/local/
├── modules/<vendor>.<module>/   # project code lives in modules (bitrix-modules)
├── components/<ns>/<name>/      # bitrix-components
├── templates/<site_tpl>/        # site templates, component template overrides
├── routes/web.php               # bitrix-routing
├── js/<vendor>/<module>/<ext>/  # extension id vendor.module.ext (bitrix-extensions)
├── activities/                  # bizproc activities (also activities/custom)
├── gadgets/<ns>/<name>/         # desktop gadgets
├── blocks/                      # landing blocks repo
├── php_interface/
│   ├── init.php                 # every hit, before page code
│   ├── dbconn.php               # Since main 24.100 here; kernel constants
│   ├── after_connect_d7.php     # after the "default" DB connect only
│   └── user_lang/<lang>/lang.php # phrase overrides
├── .settings.php                # replaces /bitrix/.settings.php (bitrix-settings)
└── .settings_extra.php
```

Give `/local/php_interface/` the same access protection as `/bitrix/php_interface/`.

## `php_interface` files

- `init.php`: only constants needed before modules, and runtime `addEventHandler()` for hooks that belong to no module. Everything else → a module (`install/`, `include.php`, `.settings.php`).
- `after_connect_d7.php` is included inside the `default` connection object: use `$this->queryExecute("SET …")` (charset, `sql_mode`, time zone). Only one file runs — a `/local` copy disables the `/bitrix` one, so carry its statements over. Other connections run their own file via `'include_after_connected' => '/abs/path.php'` in `connections`.

## Loading a module (`Loader`)

| Situation | Call |
| --- | --- |
| Module required; failure is a bug | `Loader::requireModule('vendor.module')` — throws `LoaderException` |
| Optional integration | `if (Loader::includeModule('crm')) { … }` |
| Shareware/demo status | `Loader::includeSharewareModule()` |

`includeModule()` returns `false` if the module is not registered in `b_module` (files alone are not enough) or its `include.php` returns `false`. On success it registers the PSR-4 root, includes `include.php`, registers module `services`. Load once at the entry point (controller, agent, command), not deep in services. Legacy `CModule::IncludeModule()` — compat only.

## PSR-4 in module `lib/`

- Root: `vendor.module` → `Vendor\Module\` → `lib/`; one-word `mymodule` → `Bitrix\Mymodule\`.
- Folder = namespace segment, file = class: `Vendor\Module\Public\Service\PostService` → `lib/Public/Service/PostService.php`.
- Loader tries the exact-case path, then all-lowercase (kernel mixes both styles; since main 26.600 many kernel `lib/` dirs are PascalCase). `PostTable` is also looked up as `post.php`.
- Never `require` kernel `lib/` files by path — kernel updates rename them (e.g. lists 26.200 moved to PascalCase).
- Non-PSR-4 legacy code: `Loader::registerNamespace('Vendor\\Legacy', $absDir)` or `Loader::registerAutoLoadClasses('vendor.module', ['COldClass' => 'classes/old.php'])` in `include.php`.

## Composer

- `composer.json` outside `DOCUMENT_ROOT` (e.g. project root), `.settings.php` → `'composer' => ['value' => ['config_path' => '../composer.json']]`; the kernel includes its `vendor/autoload.php`.
- Required by `bitrix.php` (Symfony Console) — `bitrix-console-commands`.
- Never install packages into `/bitrix/`.

## File priority

- Components, site templates, `php_interface` files, `.settings*.php`: `/local/` first, then `/bitrix/`.
- Component templates: site template `components/…` overrides the component's own `templates/`.
- Modules: `/local/modules/<id>` wins when both exist.
- Module routes are not auto-loaded: `require` them from `/local/routes/web.php` (`bitrix-routing`).
