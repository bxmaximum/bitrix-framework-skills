---
name: bitrix-settings
description: 'Kernel config .settings.php / .settings_extra.php — file resolution (/local replaces /bitrix), readonly, sections connections, exception_handling, cookies, crypto, smtp, ui, module .settings.php. Use when changing kernel configuration.'
---

# Kernel configuration (`.settings.php`)

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| Sections: `connections`, `exception_handling`, `cookies`, `crypto`, `ui`, … | `rules/sections.md` |
| Mail via SMTP (`smtp`, per-sender SMTP) | `rules/smtp.md` |

## Which file is read

- Main: `/local/.settings.php` if present (**Since main 24.100**), else `/bitrix/.settings.php` — only one. The `/local` file **replaces** the kernel file: copy every section, `connections` included.
- Extra: `/local/.settings_extra.php`, else `/bitrix/.settings_extra.php` — only one; loaded after the main file, each top-level section replaces the whole section (no deep merge).
- Put project sections (closures, secrets, `routing`, `loggers`, `messenger`) into `/local/.settings_extra.php`; it shadows `/bitrix/.settings_extra.php`, so move that file's sections over.
- Kernel/admin writes (`Configuration::setValue()`, settings forms) `var_export` main-file data into the main file; closures there break on the next write. The extra file is never rewritten.
- Site checker and `services/quickway.php` read only `/bitrix/.settings.php` — keep it valid. `IsConfigFile()` denies file-manager access to `/bitrix/.settings.php` only, not to `/local/.settings*.php`.
- `php_interface/dbconn.php` (`/local` **Since main 24.100**, else `/bitrix`) is still required.

## Format

`'section' => ['value' => [...], 'readonly' => true]` — `readonly` sections are skipped by runtime `Configuration::add()`/`delete()` and admin forms. Read: `Configuration::getValue('section')`.

## Global vs module

| File | Sections |
| --- | --- |
| global main/extra | `connections`, `cache`, `cache_flags`, `session`, `cookies`, `crypto`, `exception_handling`, `routing`, `messenger`, `loggers`, `composer`, `http_client_options`, `pull`, `smtp`, `ui`, `default_language`, `services` |
| `/local/modules/<id>/.settings.php` (read while the module is installed) | `services`, `controllers`, `console`, `messenger`, `rest` (v3) |

No `validation` section exists (`bitrix-validation`). `routing` is global only (`bitrix-routing`).
