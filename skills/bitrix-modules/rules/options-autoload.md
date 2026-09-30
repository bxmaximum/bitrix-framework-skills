# Options page, defaults, autoload, routes

## `default_option.php`

```php
<?php
$vendor_module_default_option = [   // variable name: module id with '.' → '_'
    'sync_interval' => '3600',
    'debug' => 'N',
];
```

`Option::get('vendor.module', 'sync_interval')` falls back to it. Option semantics, site overrides, when not to use options — `bitrix-storage`.

## Admin options page — `/local/modules/vendor.module/options.php`

Shown in *Settings → Module settings* for installed modules; included by `bitrix/admin/settings.php`, which also defines the `__AdmSettings*` helpers used below.

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
    Loc::getMessage('VENDOR_OPT_SECTION_API'),                                        // heading row
    ['api_url', Loc::getMessage('VENDOR_OPT_API_URL'), 'https://api.example', ['text', 60]],
    ['debug', Loc::getMessage('VENDOR_OPT_DEBUG'), 'N', ['checkbox']],
];

$request = Context::getCurrent()->getRequest();
if ($request->isPost() && $request->getPost('save') !== null && check_bitrix_sessid())
{
    __AdmSettingsSaveOptions($moduleId, $options); // reads the POSTed values itself
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

- Row format: `[name, title, default, [type, …]]`; types `text` (size), `password`, `checkbox`, `textarea` (rows, cols), `selectbox` (`[value => label]`); a plain string is a heading. 7th element `'Y'` = per-site values.
- Secrets (API keys) belong in `.settings.php`/env, not in options shown to every module admin (`bitrix-settings`).

## Autoload

- `Loader::includeModule('vendor.module')` registers PSR-4 root `Vendor\Module\` → `lib/` (one-word `mymodule` → `Bitrix\Mymodule\`), includes `include.php`, registers module `services`.
- Loader tries the exact-case path, then all-lowercase (`lib/Public/Service/PostService.php`, then `lib/public/service/postservice.php`); `FooTable` also resolves from `foo.php`. Pick one style per module.
- Non-PSR-4 legacy code: `Loader::registerAutoLoadClasses('vendor.module', ['CVendorOld' => 'classes/general/old.php'])` in `include.php` (paths relative to the module dir).
- Details: `bitrix-project-structure`.

## Module routes

Keep `routes/web.php` in the module and `require` it from `/local/routes/web.php`; the router never reads module `.settings.php` `routing` (`bitrix-routing`).
