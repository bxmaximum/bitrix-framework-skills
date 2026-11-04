# Поля и пользовательские поля

## Получение метаданных

```php
use Bitrix\Crm\Item;

foreach ($factory->getFieldsCollection() as $field) { // Bitrix\Crm\Field: системные поля и UF
    $field->getName();  // общее имя: TITLE, STAGE_ID, UF_CRM_5_EXT_ID
    $field->getTitle();
    $field->getType();  // Field::TYPE_* либо ID типа UF
    $field->isRequired(); $field->isMultiple(); $field->isUserField();
}
$factory->isFieldExists('UF_CRM_5_EXT_ID');
$caption = $factory->getFieldCaption(Item::FIELD_NAME_STAGE_ID);
$userFields = $factory->getUserFieldsCollection(); // только UF
$ufInfo = $factory->getUserFieldsInfo();           // массивы метаданных UF
```

- `isRequired()` задаёт постоянную обязательность; обязательность по стадиям проверяет операция.
- Массивы `getFieldsInfo()` + `getUserFieldsInfo()` соответствуют ответу REST `crm.item.fields` с именами camelCase.
- Коллекции кешируются на запрос. После добавления UF вызывай `$factory->clearUserFieldsInfoCache()` и `$factory->clearFieldsCollectionCache()`.

## Идентификаторы сущностей UF

Получай через `$factory->getUserFieldEntityId()` либо `\CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId)`; не задавай строку вручную.

| Сущность | `ENTITY_ID` |
| --- | --- |
| Лид, сделка, контакт, компания, предложение | `CRM_LEAD`, `CRM_DEAL`, `CRM_CONTACT`, `CRM_COMPANY`, `CRM_QUOTE` |
| Смарт-процесс | `CRM_{typeId}`: `ID` типа, не `entityTypeId` |
| Смарт-счёт, смарт-документ | `CRM_SMART_INVOICE`, `CRM_SMART_DOCUMENT` |
| Дело | `CRM_ACTIVITY` |

Копии в корзине используют сущности `…_SPD`; поля туда не добавляй.

## Добавление UF в установщике или миграции

```php
$entityId = $factory->getUserFieldEntityId();
$fieldName = 'UF_' . $entityId . '_EXT_ID'; // UF_CRM_DEAL_EXT_ID, UF_CRM_5_EXT_ID

$ufId = (new \CUserTypeEntity())->Add([
    'ENTITY_ID' => $entityId,
    'FIELD_NAME' => $fieldName,
    'USER_TYPE_ID' => 'string',
    'XML_ID' => 'EXT_ID',
    'MULTIPLE' => 'N',
    'MANDATORY' => 'N',
    'EDIT_FORM_LABEL' => ['en' => 'External ID', 'ru' => 'Внешний ID'],
    'LIST_COLUMN_LABEL' => ['en' => 'External ID', 'ru' => 'Внешний ID'],
]); // int ID либо false; ошибка в $APPLICATION->GetException()
$factory->clearUserFieldsInfoCache();
$factory->clearFieldsCollectionCache();
```

- REST `userfieldconfig.add` требует `UF_{ENTITY_ID}_…`. Сам `CUserTypeEntity` требует префикс `UF_`, символы `[0-9A-Z_]`, длину 4–50. Старые поля сделок выглядят как `UF_CRM_1639669411830`.
- Для идемпотентности проверяй `$factory->isFieldExists($fieldName)` до `Add()`.
- Список: `(new \CUserFieldEnum())->SetEnumValues($ufId, ['n0' => ['VALUE' => 'A', 'XML_ID' => 'A']])`. Общий API UF: `bitrix-cms-basics`.

## Значения UF элемента

```php
$item->set('UF_CRM_5_EXT_ID', 'A-17');
$value = $item->get('UF_CRM_5_EXT_ID');
$items = $factory->getItems(['filter' => ['=UF_CRM_5_EXT_ID' => 'A-17'], 'select' => ['ID']]);
```

- Множественные поля принимают и возвращают массивы. Формат определяется типом UF: список — ID значения, файл — ID или файловый массив.
- Обязательные UF проверяет операция; `disableCheckRequiredUserFields()` отключает проверку.
- Неизвестное либо выключенное для типа поле в `set()` вызывает `ArgumentException`.


## Обязательность по стадиям

**Since crm 26.1800** публичные `Bitrix\Crm\V2\Public\Command\Field\SetRequirementCommand` и `ClearRequirementCommand` изменяют правило обязательности от указанного пользователя с проверкой права управления полями. `SetRequirementCommand` принимает тип `V2\Public\EntityType`, `userId`, `fieldId`, `categoryId`, `Requirement\SetInput`; проверяй `Result`. Для сделки `categoryId=0` — основная воронка; для лида/предложения — `null`.

`SetInput::withRequiredStageIds([])` снимает дополнительное правило, `SetInput::withoutRequiredStageIds()` делает поле обязательным во всей воронке. Ограничение лицензии: `STAGE_REQUIREMENT_NOT_AVAILABLE`; технические исключения могут выйти наружу. Классы Requirement находятся в `Bitrix\Crm\V2\Public\Entity\Field\Requirement`. На старом модуле пользуйся настройкой обязательности в интерфейсе, не записывай служебные таблицы напрямую.
