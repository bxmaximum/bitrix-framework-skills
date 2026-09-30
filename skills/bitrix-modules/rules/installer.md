# Module installer

## Scaffold

`php bitrix.php make:module vendor.module --name='Vendor Module'` (**Since main 25.900**; older: create files by hand) writes into `/local/modules/vendor.module/`:

- `install/index.php`, `install/version.php`, `default_option.php`, `lang/ru/install/index.php`;
- empty `install/mysql/install.sql` / `uninstall.sql` — on main 26.600+ delete them and use `install/migrations/` (`rules/install-migrations.md`).

No `.settings.php`, no `lib/`. `dev:module-skeleton vendor.module` adds empty `lib/` layer dirs (`Controller`, `Entity`, `Internals/…`, `Service`, …).

```
/local/modules/vendor.module/
├── install/{index.php, version.php, migrations/}
├── lang/<lang>/install/index.php   # mirrors the source path
├── lib/                            # PSR-4 Vendor\Module\...
├── .settings.php                   # services, controllers, console, messenger
├── default_option.php
└── include.php                     # optional; returning false = module not loaded
```

`install/version.php`: `$arModuleVersion = ['VERSION' => '1.0.0', 'VERSION_DATE' => '2026-09-30 12:00:00'];`

## `install/index.php`

```php
<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class vendor_module extends CModule
{
    public $MODULE_ID = 'vendor.module';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('VENDOR_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('VENDOR_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = 'Vendor';
        $this->PARTNER_URI = 'https://vendor.example';
    }

    public function DoInstall(): void
    {
        if ($GLOBALS['USER']->IsAdmin() && $this->InstallDB())
        {
            $this->InstallFiles();
        }
    }

    public function DoUninstall(): void
    {
        if ($GLOBALS['USER']->IsAdmin() && $this->UnInstallDB())
        {
            $this->UnInstallFiles();
        }
    }

    public function InstallDB(): bool
    {
        $result = $this->installMigrations(); // main 26.600+, see rules/install-migrations.md
        if (!$result->isSuccess())
        {
            $GLOBALS['APPLICATION']->ThrowException(implode('<br>', $result->getErrorMessages()));
            return false;
        }
        ModuleManager::registerModule($this->MODULE_ID);

        return true;
    }

    public function UnInstallDB(bool $dropTables = true): bool
    {
        $result = $this->uninstallMigrations($dropTables); // true = DROP module tables (data loss); also unregisters events.php handlers
        if (!$result->isSuccess())
        {
            $GLOBALS['APPLICATION']->ThrowException(implode('<br>', $result->getErrorMessages()));
            return false;
        }
        Option::delete($this->MODULE_ID);                   // all module options
        ModuleManager::unRegisterModule($this->MODULE_ID);  // also removes agents and group rights

        return true;
    }
}
```

- Name/description come from `lang/<lang>/install/index.php` (`$MESS['VENDOR_MODULE_NAME'] = '…';`); a wrong path or code shows an empty name in *Settings → Modules*.
- `InstallFiles()`: `CopyDirFiles($from, $to, $rewrite = true, $recursive = false)` — pass `true` as 4th arg or subfolders are skipped: `CopyDirFiles(__DIR__ . '/components', $_SERVER['DOCUMENT_ROOT'] . '/local/components', true, true)`.
- `UnInstallFiles()`: `DeleteDirFilesEx('/local/components/vendor')` (path from `DOCUMENT_ROOT`, recursive). `DeleteDirFiles($fromDir, $toDir)` only unlinks top-level files of `$toDir` named like those in `$fromDir` — fine for `admin/` stubs, not for directories.
- **Since main 26.700** `registerModule()` is idempotent (duplicate ignored); older: guard with `ModuleManager::isModuleInstalled()`.
- `unRegisterModule()` removes agents (`CAgent::RemoveModuleAgents`) and group rights only; tables, event handlers, options and files are yours to remove.

## Fallback before main 26.600

No `installMigrations()`: register first (module classes autoload only after registration), then create state yourself — each call idempotent.

```php
ModuleManager::registerModule($this->MODULE_ID);
\Bitrix\Main\Loader::includeModule($this->MODULE_ID);

$connection = \Bitrix\Main\Application::getConnection();
if (!$connection->isTableExists(PostTable::getTableName()))
{
    PostTable::getEntity()->createDbTable();
}
\Bitrix\Main\EventManager::getInstance()->registerEventHandler('main', 'OnAfterUserAdd', $this->MODULE_ID, UserHandler::class, 'onAfterAdd');
\CAgent::AddAgent(SyncAgent::class . '::run();', $this->MODULE_ID, 'N', 3600, existError: false);
```

Uninstall mirrors it: `unRegisterEventHandler()` with identical args, `$connection->dropTable(PostTable::getTableName())` (**destroys data** — offer "keep tables"), `Option::delete()`, `unRegisterModule()`.

- `registerEventHandler()` is idempotent (insert-ignore by handler hash).
- `main:OnAfterUserAdd` is a legacy event: the handler gets `array &$arFields`, not `Event` (`bitrix-events`).
