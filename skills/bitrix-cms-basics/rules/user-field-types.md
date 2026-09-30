# Custom user field types

A type = class extending `Bitrix\Main\UserField\Types\BaseType` (or a concrete type such as `StringType`) + a render component. Extending a concrete type inherits its DB column, settings and markup.

## Type class

```php
namespace Vendor\Module\UserField;

use Bitrix\Main\UserField\Types\StringType;

final class CodeType extends StringType
{
    public const USER_TYPE_ID = 'vendor_code';
    // keep parent RENDER_COMPONENT (bitrix:main.field.string) to reuse its markup

    public static function getDescription(): array
    {
        return [
            'DESCRIPTION' => 'Product code',
            'BASE_TYPE' => \CUserTypeManager::BASE_TYPE_STRING,
        ];
    }

    public static function checkFields(array $userField, $value): array
    {
        $errors = parent::checkFields($userField, $value);
        if ((string)$value !== '' && !preg_match('/^[A-Z0-9-]+$/', (string)$value)) {
            $errors[] = ['id' => $userField['FIELD_NAME'], 'text' => 'Code: A-Z, 0-9, "-"'];
        }

        return $errors;
    }
}
```

- `getUserTypeDescription()` merges `USER_TYPE_ID`, `CLASS_NAME`, `EDIT_CALLBACK`/`VIEW_CALLBACK` (→ render component), `USE_FIELD_COMPONENT` with your `getDescription()` (`DESCRIPTION`, `BASE_TYPE`: `CUserTypeManager::BASE_TYPE_INT|FILE|ENUM|DOUBLE|DATETIME|STRING`).
- Directly on `BaseType` implement `getDbColumnType(): string` (abstract; build it via `Application::getConnection()->getSqlHelper()->getColumnTypeByField(new StringField('x'))` as kernel types do).
- Overridables: `prepareSettings(array $userField): array` (whitelist `SETTINGS`), `checkFields(array $userField, $value): array` (list of `['id' => FIELD_NAME, 'text' => …]`), `getPublicText()`, `getDefaultValue()`, `isMandatorySupported()`, `isMultiplicitySupported()`.

## Registration

The manager collects types from `main:OnUserTypeBuildList` once per request (`GetUserType()` caches) — register before the first UF call; the last handler for a `USER_TYPE_ID` wins.

```php
// /local/php_interface/init.php or module include.php
\Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible(
    'main', 'OnUserTypeBuildList', [\Vendor\Module\UserField\CodeType::class, 'getUserTypeDescription']
);
```

Module: `registerEventHandlerCompatible('main', 'OnUserTypeBuildList', 'vendor.module', CodeType::class, 'getUserTypeDescription')` in `DoInstall()`, `unRegisterEventHandler()` with the same arguments in `DoUninstall()` (`bitrix-modules`). Check: `$USER_FIELD_MANAGER->GetUserType('vendor_code')` returns an array, not `false`.

## Own render component

Set `RENDER_COMPONENT = 'vendor:field.code'` (without `:` the kernel prefixes `bitrix:`). Then the parent's templates are no longer used.

```php
// /local/components/vendor/field.code/class.php
final class VendorFieldCodeComponent extends \Bitrix\Main\Component\BaseUfComponent
{
    protected static function getUserTypeId(): string
    {
        return \Vendor\Module\UserField\CodeType::USER_TYPE_ID;
    }
}
```

- Template folder = mode: `main.view`, `main.edit` (public; `BaseType::MODE_VIEW` / `MODE_EDIT`), `main.edit_form`, `main.admin_settings`, `main.filter_html`, `main.admin_list_view_html`, `main.admin_list_edit_html`, `main.public_text`. Page `.default.php`, or `mobile.php` when `additionalParameters['mediaType'] = 'mobile'`. Missing mode folder → `.default` folder → error.
- `$arResult`: `userField`, `fieldName` (`[]` appended for multiple), `value` (always an array), `additionalParameters`.
- Settings form (`main.admin_settings`): name inputs `<?= $arResult['additionalParameters']['NAME'] ?>[PREFIX]` (the manager passes `NAME = 'SETTINGS'`), then normalize in `prepareSettings()`.
- `additionalParameters['FORCE_USE_VALUE'] = 'Y'` shows `VALUE` instead of `DEFAULT_VALUE` for a new entity — **Since main 26.250\***; fallback: pass `VALUE` in the additional parameters.
- UF types `iblock_element` / `iblock_section` render via `bitrix:iblock.field.element` / `bitrix:iblock.field.section`; `main.field.element` / `main.field.section` are template-only stubs (docs say otherwise — follow the kernel).
