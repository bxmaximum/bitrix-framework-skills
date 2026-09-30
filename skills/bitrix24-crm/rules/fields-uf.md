# Fields and user fields

## Discovering fields

```php
use Bitrix\Crm\Item;

foreach ($factory->getFieldsCollection() as $field) { // Bitrix\Crm\Field, system + UF
    $field->getName();  // common name: TITLE, STAGE_ID, UF_CRM_5_EXT_ID
    $field->getTitle();
    $field->getType();  // Field::TYPE_* or UF type id
    $field->isRequired(); $field->isMultiple(); $field->isUserField();
}
$factory->isFieldExists('UF_CRM_5_EXT_ID');
$caption = $factory->getFieldCaption(Item::FIELD_NAME_STAGE_ID);
$userFields = $factory->getUserFieldsCollection(); // UF only
$ufInfo = $factory->getUserFieldsInfo();           // UF metadata arrays
```

- `isRequired()` is static required-ness; stage-dependent required fields are enforced by operations.
- `getFieldsInfo()` + `getUserFieldsInfo()` arrays are what REST `crm.item.fields` returns (camelCased).
- Collections are cached per hit; after adding a UF call `$factory->clearUserFieldsInfoCache()` and `$factory->clearFieldsCollectionCache()`.

## UF entity IDs

Take it from `$factory->getUserFieldEntityId()` (or `\CCrmOwnerType::ResolveUserFieldEntityID($entityTypeId)`), never hardcode.

| Entity | `ENTITY_ID` |
| --- | --- |
| Lead, Deal, Contact, Company, Quote | `CRM_LEAD`, `CRM_DEAL`, `CRM_CONTACT`, `CRM_COMPANY`, `CRM_QUOTE` |
| Smart process | `CRM_{typeId}`: the type `ID`, not `entityTypeId` |
| Smart invoice, smart document | `CRM_SMART_INVOICE`, `CRM_SMART_DOCUMENT` |
| Activity | `CRM_ACTIVITY` |

Recycle-bin copies use `…_SPD` entities; don't add fields there.

## Adding a UF (installer or migration)

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
]); // int ID or false ($APPLICATION->GetException())
$factory->clearUserFieldsInfoCache();
$factory->clearFieldsCollectionCache();
```

- Name rule of REST `userfieldconfig.add`: `UF_{ENTITY_ID}_…`; `CUserTypeEntity` itself requires `UF_`, `[0-9A-Z_]`, 4–50 chars. Legacy deal fields look like `UF_CRM_1639669411830`.
- Idempotent: check `$factory->isFieldExists($fieldName)` before `Add()`.
- Enumerations: `(new \CUserFieldEnum())->SetEnumValues($ufId, ['n0' => ['VALUE' => 'A', 'XML_ID' => 'A']])`. General UF API: `bitrix-cms-basics`.

## UF values on items

```php
$item->set('UF_CRM_5_EXT_ID', 'A-17');
$value = $item->get('UF_CRM_5_EXT_ID');
$items = $factory->getItems(['filter' => ['=UF_CRM_5_EXT_ID' => 'A-17'], 'select' => ['ID']]);
```

- Multiple fields take/return arrays. Value format is that of the UF type (enum → value ID, file → file ID or file array).
- Mandatory UF are checked by operations (`disableCheckRequiredUserFields()` to skip).
- Unknown or type-disabled field names in `set()` throw `ArgumentException`.
