# Helpers and builders

`$helper = $this->getHelperManager();` then `$helper->Iblock()->…`. A helper throws `HelperException` when its Bitrix module is not installed.

## Helpers

| Call | Covers | Main methods |
| --- | --- | --- |
| `Iblock()` | Types, iblocks, fields, properties, sections, elements, rights | `saveIblockType`, `saveIblock`, `saveProperty`, `saveSection`, `saveElementByXmlId`, `deleteIblockIfExists` |
| `Hlblock()` | HL blocks, UF fields, elements, rights | `saveHlblock`, `saveField`, `saveElementByXmlId`, `deleteHlblockIfExists` |
| `UserTypeEntity()` | UF of any `ENTITY_ID` | `saveUserTypeEntity`, `addUserTypeEntitiesIfNotExists`, `deleteUserTypeEntitiesIfExists` |
| `UserGroup()` | Groups by `STRING_ID` | `saveGroup($code, $fields)`, `deleteGroup` |
| `Agent()` | Agents | `saveAgent`, `deleteAgentIfExists($moduleId, $name)` |
| `Option()` | Module options | `saveOption(['MODULE_ID', 'NAME', 'VALUE', 'SITE_ID'])`, `deleteOptions($filter)` |
| `Event()` | Mail event types, templates, SMS (`bitrix-mail`) | `saveEventType($eventName, $fields)` (`LID` = language id, not site), `saveEventMessage($eventName, $fields)`, `saveEventSmsTemplate` |
| `Site()` | Sites (read) and site templates | `getSites`, `getDefaultSiteIdIfExists`, `setSiteTemplates($siteId, $templates)` |
| `Lang()` / `Culture()` | Languages, cultures | `saveLang(['LID', …])`, `saveCulture(['CODE', …])` |
| `Form()` / `Forum()` / `Blog()` / `Vote()` / `Subscribe()` | Those modules | module-specific `save*` |
| `OrderProperties()` / `SaleDiscount()` / `DeliveryService()` | `sale` | `save*` |
| `Medialib()`, `UserOptions()`, `Sql()`, `Text()` | Media library, grid/filter options, SQL, text utils | |
| `IblockExchange()` / `HlblockExchange()` / `MedialibExchange()` / `BlogExchange()` | Element exchange for builders | |
| `Task()` | Access levels `b_task` (`getTaskIdByName($moduleId, $name)`), not the Tasks module | |

Gotchas:

- `Site()` cannot create or update sites. Create languages with `Lang()->saveLang()` and cultures with `Culture()->saveCulture()`.
- `deleteOptions(['MODULE_ID' => 'x'])` without `NAME` deletes **all** options of the module.
- `saveEventMessage()` matches an existing template by event name + `SUBJECT`: a changed subject in a later migration creates a second template. To change a subject, find the row by the old one (`getEventMessage(['EVENT_NAME' => $name, 'SUBJECT' => $oldSubject])`) and call `updateEventMessageById($row['ID'], $fields)`.

Domain rules for iblocks/HL beyond migrations: `bitrix-iblocks`, `bitrix-highloadblock`.

## Method naming

| Pattern | Behavior |
| --- | --- |
| `save*` | Create or update to the given state (default for schema) |
| `add*IfNotExists` | Create once; never updates drifted fields |
| `get*IfExists` | Return, or **throw** `HelperException` when missing |
| `delete*IfExists`, `update*IfExists` | Return `false` when missing |

## Builders

Builders generate a Version (and exchange files) from the current database. Run from admin or `php "$MIGRATE" run <Builder>`, review the generated PHP, commit, then `up` elsewhere.

Default keys (config `version_builders`): `BlankBuilder`, `IblockBuilder`, `IblockPropertyBuilder`, `IblockPropertyDeleteBuilder`, `IblockCategoryBuilder`, `IblockElementsBuilder`, `IblockDeleteBuilder`, `HlblockBuilder`, `HlblockElementsBuilder`, `UserTypeEntitiesBuilder`, `UserGroupBuilder`, `AgentBuilder`, `OptionBuilder`, `EventBuilder`, `FormBuilder`, `ForumBuilder`, `VoteBuilder`, `SubscribeBuilder`, `BlogBuilder`, `BlogPostBuilder`, `BlogUserTypeEntitiesBuilder`, `UserOptionsBuilder`, `LanguageBuilder`, `OrderPropertiesBuilder`, `SaleDiscountBuilder`, `MedialibElementsBuilder`, `CacheCleanerBuilder`, `MarkerBuilder`, `TransferBuilder`.

Samples (read-only): `{module}/examples/*.php`, `{module}/templates/*.php`.

## Custom helpers

```php
\Sprint\Migration\HelperManager::getInstance()->registerHelper('Shop', \Vendor\Shop\Migration\ShopHelper::class);
// class ShopHelper extends \Sprint\Migration\Helper; then $helper->Shop()->…
```

Only for a shared migration layer; otherwise call module services from `up()`.
