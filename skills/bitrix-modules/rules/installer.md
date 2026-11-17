# Установщик модуля

## Генерация каркаса

`php bitrix.php make:module vendor.module --name='Vendor Module'` (**Since main 25.900**; раньше создавай файлы вручную) пишет в `/local/modules/vendor.module/`:

- `install/index.php`, `install/version.php`, `default_option.php`, `lang/ru/install/index.php`;
- Пустые `install/mysql/install.sql` / `uninstall.sql`: на main 26.600+ удали их и используй `install/migrations/` (`rules/install-migrations.md`).

`make:module` не создаёт `.settings.php` и `lib/`.

```
/local/modules/vendor.module/
├── install/{index.php, version.php, migrations/}
├── lang/<lang>/install/index.php   # повторяет путь исходного файла
├── lib/                            # PSR-4 Vendor\Module\...
├── .settings.php                   # services, controllers, console, messenger
├── default_option.php
└── include.php                     # необязателен; return false означает отказ загрузки
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
        $result = $this->installMigrations(); // main 26.600+, см. rules/install-migrations.md
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
        $result = $this->uninstallMigrations($dropTables); // true = DROP таблиц модуля (потеря данных); также снимает регистрации events.php
        if (!$result->isSuccess())
        {
            $GLOBALS['APPLICATION']->ThrowException(implode('<br>', $result->getErrorMessages()));
            return false;
        }
        Option::delete($this->MODULE_ID);                   // все настройки модуля
        ModuleManager::unRegisterModule($this->MODULE_ID);  // также удаляет агенты и групповые права

        return true;
    }
}
```

- Имя/описание берутся из `lang/<lang>/install/index.php` (`$MESS['VENDOR_MODULE_NAME'] = '…';`); неверный путь или код дают пустое имя в «Настройки → Модули».
- `InstallFiles()`: `CopyDirFiles($from, $to, $rewrite = true, $recursive = false)` — передавай `true` четвёртым аргументом для копирования подкаталогов: `CopyDirFiles(__DIR__ . '/components', $_SERVER['DOCUMENT_ROOT'] . '/local/components', true, true)`.
- `UnInstallFiles()`: `DeleteDirFilesEx('/local/components/vendor')` (путь от `DOCUMENT_ROOT`, рекурсивно). `DeleteDirFiles($fromDir, $toDir)` удаляет только файлы верхнего уровня `$toDir`, имена которых есть в `$fromDir`: подходит для заглушек `admin/`, но не каталогов.
- **Since main 26.700** `registerModule()` идемпотентен (повтор игнорируется); раньше проверяй `ModuleManager::isModuleInstalled()`.
- `unRegisterModule()` удаляет только агенты (`CAgent::RemoveModuleAgents`) и групповые права. Таблицы, обработчики, настройки и файлы удаляй самостоятельно.

## До main 26.600

Метода `installMigrations()` нет: сначала регистрируй модуль (до этого его классы не загружаются), затем создавай состояние самостоятельно. Каждый вызов должен быть идемпотентным.

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

Удаление симметрично: `unRegisterEventHandler()` с теми же аргументами, `$connection->dropTable(PostTable::getTableName())` (**уничтожает данные** — предложи сохранить таблицы), `Option::delete()`, `unRegisterModule()`.

- `registerEventHandler()` идемпотентен: вставка с игнорированием по хешу обработчика.
- `main:OnAfterUserAdd` — старое событие: обработчик получает `array &$arFields`, не `Event` (`bitrix-events`).
