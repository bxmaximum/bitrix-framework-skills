# CLI and configs

## Module path

```bash
# from document root
if [ -f local/modules/sprint.migration/tools/migrate.php ]; then
  MIGRATE=local/modules/sprint.migration/tools/migrate.php
else
  MIGRATE=bitrix/modules/sprint.migration/tools/migrate.php
fi
php "$MIGRATE" <command> [args] [options]
```

CLI SAPI only; it boots Bitrix through `prolog_before.php` (sets `DOCUMENT_ROOT` from its own path if empty) and installs the module if its files exist but it is not registered. Symfony: `php bin/console sprint:migration` when the bundle is registered.

## Commands

Full list: `{module}/commands-en.txt`.

| Command | Purpose |
| --- | --- |
| `add [desc] [name]` | Blank migration (`--desc=`, `--name=`) |
| `ls` | List (`--new`, `--installed`, `--search=`, `--tag=`) |
| `up` | Apply all new (`--search=`, `--tag=`, `--add-tag=`) |
| `up <version>` | Apply one |
| `down <version>` | Roll back one |
| `down` | **DANGER:** rolls back every installed migration (optionally filtered by `--search=`/`--tag=`) |
| `redo <version>` | `down` then `up` |
| `run <builder>` | Run a builder (`rules/helpers-builders.md`) |
| `mark <version\|new\|installed\|unknown> --as=installed\|new` | Change status without running code |
| `delete <version\|new\|installed\|unknown>` | **DANGER:** deletes migration files/records |
| `config`, `--config=<name>` | Show / switch config |

```bash
php "$MIGRATE" add "Add news iblock" NewsIblock
php "$MIGRATE" ls --new
php "$MIGRATE" up
php "$MIGRATE" down Version20260720113800   # always name the version
php "$MIGRATE" --config=shop up
```

## Version naming

- Valid PHP class name; class name = file name; namespace `Sprint\Migration`.
- Contains a timestamp in `version_timestamp_format` (default `YmdHis`).
- Template `version_name_template` = `#NAME##TIMESTAMP#` → `Version20260720113800`, `NewsIblock20260720113800`.

## Configs

The module reads `migrations.<name>.php` from `local/php_interface` (or `bitrix/php_interface` without it) plus dirs returned by handlers of module event `OnSearchConfigFiles`. The file returns an array. `migrations.cfg.php` replaces the default `cfg` config.

`migration_dir` and `migration_table` are **not** derived from `<name>`. A file without them shares the default dir and table with `cfg`. Always set both:

```php
<?php
return [
    'title' => 'Shop migrations',
    'migration_dir' => '/local/php_interface/migrations.shop',   // from docroot unless migration_dir_absolute
    'migration_table' => 'sprint_migration_shop',
    // 'exchange_dir' => … (default = migration_dir)
    // 'console_user' => 'admin' | 'login:deploy' | false,
    // 'version_builders' => [...], 'migration_extend_class' => 'Version',
];
```

The admin "create config" button writes exactly these two keys. The module options page only creates/deletes config files; it stores no config values.

## Console user

- `console_user` (default `admin`): `admin` = first active user of the admin group, `login:<login>` = that user, `false` = no authorization.
- `console_auth_events_disable` (default `true`) skips auth event handlers in CLI.
- Admin UI and CLI share files and the versions table; automation uses CLI.
