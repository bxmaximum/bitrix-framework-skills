# Links between blocks, iblock directory property

## UF type `hlblock`

Stores the target record's numeric `ID`. `SETTINGS` (invalid values fall back to defaults):

| Key | Default | Meaning |
| --- | --- | --- |
| `HLBLOCK_ID` | 0 | target block (required for links) |
| `HLFIELD_ID` | 0 | UF id shown as label, 0 = `ID` |
| `DISPLAY` | `CUserTypeHlblock::DISPLAY_LIST` | `DISPLAY_CHECKBOX`, `DISPLAY_UI`, `DISPLAY_DIALOG` |
| `LIST_HEIGHT` | 1 single / 5 multiple | list size |
| `DEFAULT_VALUE` | `''` / `[]` | int / int[] |

`MULTIPLE` belongs to the field, not `SETTINGS`. `DISPLAY_DIALOG` uses UI entity selector entity `highloadblock-element` (options `highloadblockId`, `valueField`, `titleField`, `orderField`, `direction`, `queryMethod`; see `bitrix-ui`).

- Single field → auto reference `UF_X_REF`. It exists only when `HLBLOCK_ID` is set and the target block exists. Compiling the source also compiles the target.

```php
$rows = $dataClass::getList([
    'select' => ['ID', 'CAT_NAME' => 'UF_CATEGORY_REF.UF_NAME'],
    'filter' => ['=UF_CATEGORY_REF.UF_ACTIVE' => 1],
])->fetchAll();
```

- Multiple field → no `_REF`. The value is an array of IDs; `UF_X_SINGLE` filters by one value (JOIN). To load targets, collect IDs and run one query with `'@ID' => $ids`.
- No foreign keys: target existence is never checked and dangling links are never cleaned. Validate before save. On target delete pick one rule: block / clear links / replace with fallback / tolerate. Check-then-delete is not atomic. Check rights on both blocks.
- Self-reference (`UF_PARENT` tree) is allowed: reject self-links, walk parents with a depth limit.
- Across environments: resolve the target by a stable code (`UF_CODE`) in the target environment, store its local `ID`.

## Iblock `directory` property (Справочник)

Type class `CIBlockPropertyDirectory` lives in **highloadblock** (registered on iblock `OnIBlockPropertyBuildList`): load both modules. The value stored is the HL record's **`UF_XML_ID`** (string), not `ID`.

```php
use Bitrix\Iblock\PropertyTable;

$property = new \CIBlockProperty();
$propId = $property->Add([
    'IBLOCK_ID' => $iblockId, 'NAME' => 'Color', 'CODE' => 'COLOR',
    'PROPERTY_TYPE' => PropertyTable::TYPE_STRING,
    'USER_TYPE' => PropertyTable::USER_TYPE_DIRECTORY,            // 'directory'
    'USER_TYPE_SETTINGS' => ['TABLE_NAME' => $hl['TABLE_NAME']], // exact TABLE_NAME of the block
    'MULTIPLE' => 'N',                                           // multiplicity here, not in settings
]);
if (!$propId) { /* $property->LAST_ERROR */ }

\CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, ['COLOR' => 'red']); // no result: re-read to verify
```

- Directory HL fields: `UF_XML_ID` (required, stable, unique — nothing enforces it), `UF_NAME`, `UF_SORT`, `UF_FILE`, `UF_DEF`, `UF_DESCRIPTION`, `UF_FULL_DESCRIPTION`, `UF_LINK`. Blocks created from property settings UI get `TABLE_NAME` `b_hlbd_<name>` cut to 30 chars; API-created blocks keep yours.
- Options need `UF_XML_ID` (absent → empty list). Order: `UF_SORT`, then `UF_NAME` (else `UF_XML_ID`), then `ID`. Without `UF_NAME` the label is `UF_XML_ID`.
- A record with `UF_DEF = 1` becomes the property `DEFAULT_VALUE` (first by `UF_SORT`, `UF_NAME`).
- Resolve values: read the property → `$dataClass::getList(['filter' => ['@UF_XML_ID' => $values]])`. Validate incoming values the same way; value order is not meaningful.
- After a `TABLE_NAME` rename: read the full `USER_TYPE_SETTINGS`, replace `TABLE_NAME`, `CIBlockProperty::Update()`. Otherwise options stop loading.
- Lookups by `TABLE_NAME` are cached (ORM 86400 s + static); `HighloadBlockTable::cleanCache()` resets the static part.
