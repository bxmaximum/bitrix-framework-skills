# Собственные типы пользовательских полей

Тип — класс-наследник `Bitrix\Main\UserField\Types\BaseType` либо готового типа (`StringType`) и компонент вывода. Наследование готового типа сохраняет его столбец БД, настройки и разметку.

## Класс типа

```php
namespace Vendor\Module\UserField;

use Bitrix\Main\UserField\Types\StringType;

final class CodeType extends StringType
{
    public const USER_TYPE_ID = 'vendor_code';
    // сохрани родительский RENDER_COMPONENT (bitrix:main.field.string) для его разметки

    public static function getDescription(): array
    {
        return [
            'DESCRIPTION' => 'Код товара',
            'BASE_TYPE' => \CUserTypeManager::BASE_TYPE_STRING,
        ];
    }

    public static function checkFields(array $userField, $value): array
    {
        $errors = parent::checkFields($userField, $value);
        if ((string)$value !== '' && !preg_match('/^[A-Z0-9-]+$/', (string)$value)) {
            $errors[] = ['id' => $userField['FIELD_NAME'], 'text' => 'Код: A-Z, 0-9, "-"'];
        }

        return $errors;
    }
}
```

- `getUserTypeDescription()` объединяет `USER_TYPE_ID`, `CLASS_NAME`, `EDIT_CALLBACK`/`VIEW_CALLBACK` (компонент вывода), `USE_FIELD_COMPONENT` с результатом `getDescription()` (`DESCRIPTION`, `BASE_TYPE`: `CUserTypeManager::BASE_TYPE_INT|FILE|ENUM|DOUBLE|DATETIME|STRING`).
- При прямом наследовании `BaseType` реализуй абстрактный `getDbColumnType(): string`; как типы ядра, используй `Application::getConnection()->getSqlHelper()->getColumnTypeByField(new StringField('x'))`.
- Переопределяемые методы: `prepareSettings(array $userField): array` (разрешённые `SETTINGS`), `checkFields(array $userField, $value): array` (список `['id' => FIELD_NAME, 'text' => …]`), `getPublicText()`, `getDefaultValue()`, `isMandatorySupported()`, `isMultiplicitySupported()`.

## Регистрация

Менеджер собирает типы из `main:OnUserTypeBuildList` один раз за запрос (`GetUserType()` кешируется). Регистрируй до первого обращения к UF; последний обработчик данного `USER_TYPE_ID` побеждает.

```php
// /local/php_interface/init.php либо include.php модуля
\Bitrix\Main\EventManager::getInstance()->addEventHandlerCompatible(
    'main', 'OnUserTypeBuildList', [\Vendor\Module\UserField\CodeType::class, 'getUserTypeDescription']
);
```

Модуль: `registerEventHandlerCompatible('main', 'OnUserTypeBuildList', 'vendor.module', CodeType::class, 'getUserTypeDescription')` в `DoInstall()`; `unRegisterEventHandler()` с теми же аргументами в `DoUninstall()` (`bitrix-modules`). Проверка: `$USER_FIELD_MANAGER->GetUserType('vendor_code')` возвращает массив, а не `false`.

## Собственный компонент вывода

Задай `RENDER_COMPONENT = 'vendor:field.code'`; без `:` ядро добавит `bitrix:`. Шаблоны родителя больше не используются.

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

- Каталог шаблона — режим: `main.view`, `main.edit` (публичные; `BaseType::MODE_VIEW` / `MODE_EDIT`), `main.edit_form`, `main.admin_settings`, `main.filter_html`, `main.admin_list_view_html`, `main.admin_list_edit_html`, `main.public_text`. Файл `.default.php` либо `mobile.php` при `additionalParameters['mediaType'] = 'mobile'`. Поиск без нужного режима: каталог `.default`, затем ошибка.
- `$arResult`: `userField`, `fieldName` (с `[]` для множественного поля), `value` (всегда массив), `additionalParameters`.
- Настройки (`main.admin_settings`): имена полей `<?= $arResult['additionalParameters']['NAME'] ?>[PREFIX]` (менеджер передаёт `NAME = 'SETTINGS'`); нормализуй в `prepareSettings()`.
- `additionalParameters['FORCE_USE_VALUE'] = 'Y'` показывает `VALUE` вместо `DEFAULT_VALUE` новой сущности — **Since main 26.250\***. На старом ядре передай `VALUE` в дополнительных параметрах.
- Типы UF `iblock_element` / `iblock_section` выводятся через `bitrix:iblock.field.element` / `bitrix:iblock.field.section`; `main.field.element` / `main.field.section` содержат только шаблоны (документация расходится с ядром).

## Очистка ресурсов — Since main 26.1000

Тип BaseType может переопределить `onBeforeDeleteValues(array $userField, iterable $savedValuesFromServer): Result` и `onBeforeDeleteField(array $userField): Result`. Первый освобождает ресурсы сохранённых значений, второй — описание типа. Используй переданные сервером значения, не данные запроса. Неуспешный Result прерывает удаление; исключение оборачивается в USER_FIELD_DELETE_FAILED. Планируй очистку идемпотентно: удаление внешнего ресурса не откатывается транзакцией БД.

Встроенные file и enumeration очищаются ядром. Это не общий after-delete: например, highloadblock 25.100 удаляет свои записи/схему отдельным кодом и эти хуки не вызывает. На старом main используй обработчик удаления своей сущности / OnBeforeUserTypeDelete и проверяй поведение конкретного хранилища.
