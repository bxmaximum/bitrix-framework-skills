# Страница настроек, значения по умолчанию, загрузка и маршруты

## `default_option.php`

```php
<?php
$vendor_module_default_option = [   // имя переменной: ID модуля с заменой '.' на '_'
    'sync_interval' => '3600',
    'debug' => 'N',
];
```

`Option::get('vendor.module', 'sync_interval')` использует это значение по умолчанию. Семантика настроек, значения по сайтам и ограничения — `bitrix-storage`.

## Административная страница настроек `/local/modules/vendor.module/options.php`

Показывается в «Настройки → Настройки модулей» для установленных модулей; подключается через `bitrix/admin/settings.php`, который определяет используемые ниже `__AdmSettings*`.

```php
<?php
/** @var CMain $APPLICATION */
use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
$moduleId = 'vendor.module';
if ($APPLICATION->GetGroupRight($moduleId) < 'W')
{
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$options = [
    Loc::getMessage('VENDOR_OPT_SECTION_API'),                                        // строка заголовка
    ['api_url', Loc::getMessage('VENDOR_OPT_API_URL'), 'https://api.example', ['text', 60]],
    ['debug', Loc::getMessage('VENDOR_OPT_DEBUG'), 'N', ['checkbox']],
];

$request = Context::getCurrent()->getRequest();
if ($request->isPost() && $request->getPost('save') !== null && check_bitrix_sessid())
{
    __AdmSettingsSaveOptions($moduleId, $options); // сам читает переданные POST-значения
}

$tabControl = new CAdminTabControl('tabControl', [['DIV' => 'edit1', 'TAB' => Loc::getMessage('MAIN_TAB_SET'), 'TITLE' => '']]);
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->Begin(); $tabControl->BeginNextTab(); __AdmSettingsDrawList($moduleId, $options); $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage('MAIN_SAVE') ?>" class="adm-btn-save">
    <?php $tabControl->End(); ?>
</form>
```

- Формат строки: `[name, title, default, [type, …]]`; типы `text` (размер), `password`, `checkbox`, `textarea` (строки, столбцы), `selectbox` (`[value => label]`); обычная строка — заголовок. Седьмой элемент `'Y'` включает значения по сайтам.
- Секреты (ключи API) храни в `.settings.php`/env, не в настройках, доступных каждому администратору модуля (`bitrix-settings`).

## Автозагрузка

- `Loader::includeModule('vendor.module')` регистрирует корень PSR-4 `Vendor\Module\` → `lib/` (однословный `mymodule` → `Bitrix\Mymodule\`), подключает `include.php` и регистрирует `services` модуля.
- Обязательные зависимости подключай в `include.php` модуля через `Loader::includeModule()`. При сбое верни `false`. Опциональные зависимости подключай на входе в интеграцию.
- Для совместимости `Loader` после точного пути пробует его строчную форму. Для `FooTable` он также проверяет `Foo.php` и `foo.php`.
- Для старого кода без PSR-4: `Loader::registerAutoLoadClasses('vendor.module', ['CVendorOld' => 'classes/general/old.php'])` в `include.php` (пути от каталога модуля).
- Подробнее: `bitrix-project-structure`.

## Маршруты модуля

Храни `routes/web.php` в модуле и подключай через `require` из `/local/routes/web.php`; маршрутизатор не читает секцию `routing` модульного `.settings.php` (`bitrix-routing`).
