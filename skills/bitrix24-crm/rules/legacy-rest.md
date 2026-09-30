# Legacy API and REST equivalents

## Legacy → D7

In crm 26.800 `CCrmLead`/`CCrmDeal`/`CCrmContact`/`CCrmCompany` `Add()`/`Update()`/`Delete()` always delegate to Factory operations through `Crm\Entity\Compatibility\Adapter` (`*Settings::isFactoryEnabled()` returns `true`). Differences: the legacy adapter does not start robots and business processes (`setRunAutomation(false)`, `setRunBizProc(false)`; old callers start them by hand), and errors come back as a `LAST_ERROR` string. Switching to D7 operations therefore starts robots: `disableAutomation()` if the old flow did not. Keep legacy calls only in code you are not touching.

| Legacy (compat only) | D7 |
| --- | --- |
| `(new \CCrmDeal(false))->Add($fields)` | `$factory->getAddOperation($item, $context)` + `disableCheckAccess()` |
| `->Update($id, $fields)`, `->Delete($id)` | `getUpdateOperation()`, `getDeleteOperation()` |
| `\CCrmDeal::GetListEx()`, `GetByID()` | `getItems()`, `getItem()`, `getItemsFilteredByPermissions()` |
| `\CCrmFieldMulti::GetList()`, `SetFields()` | `Item::getFm()`/`setFm()` |
| `\CCrmProductRow::SaveRows()` | `Item::setProductRowsFromArrays()` + update operation |
| `\CCrmPerms`, `\CCrmDeal::CheckReadPermission()`, `\CCrmAuthorizationHelper` | `getUserPermissions()->item()`/`entityType()` |
| `\CCrmActivity::Add()` for to-dos | `Activity\Entity\ToDo` |
| `\CCrmStatus` for stages | read: `$factory->getStages()`; write: still `CCrmStatus` |
| `\CCrmInvoice` (type 5) | smart invoices (31) via factory |
| `DealTable`/`LeadTable`/`ContactTable`/`CompanyTable::add/update/delete` | never; read-only at most |

Field keys: legacy arrays use entity columns (`STATUS_ID` for leads, `DATE_CREATE`, `CREATED_BY_ID`); D7 uses `Item::FIELD_NAME_*` (`STAGE_ID`, `CREATED_TIME`, `CREATED_BY`). `$item->getCompatibleData()` / `setFromCompatibleData()` convert.

## D7 ↔ REST

Cloud portals have no PHP access: the same logic goes through REST (`bitrix24-rest-api` for calling it). REST handlers run the same operations with REST context.

| D7 (box) | REST |
| --- | --- |
| `getItems()`, `getItem()` | `crm.item.list`, `crm.item.get` (`entityTypeId`) |
| add/update/delete operations | `crm.item.add`, `crm.item.update`, `crm.item.delete` |
| `getFieldsInfo()` | `crm.item.fields` |
| `getTypeByEntityTypeId()`, type ORM object | `crm.type.getByEntityTypeId`, `crm.type.list`, `crm.type.add` |
| `getCategories()`, `createCategory()` | `crm.category.list`, `crm.category.add` (`entityTypeId`) |
| `getStages()`, `CCrmStatus` | `crm.status.list`, `crm.status.add` (`ENTITY_ID` = `getStagesEntityId()`) |
| `CommentEntry::create()` | `crm.timeline.comment.add` |
| `Activity\Entity\ToDo` | `crm.activity.todo.add` |
| `CUserTypeEntity` + `getUserFieldEntityId()` | `userfieldconfig.add` (`moduleId: crm`) |
| `onCrmDynamicItem*`, `OnAfterCrm*` | REST events `onCrmDynamicItemAdd`…, `onCrmDealAdd`… |
| `WebHookTrigger::execute()` | `crm.automation.trigger` (webhook), `crm.automation.trigger.execute` (app) |

- REST uses camelCase names (`stageId`, `assignedById`, `ufCrm5_…`); D7 uses UPPER_SNAKE.
- Legacy REST `crm.deal.*`, `crm.lead.*` map to the legacy classes above; prefer `crm.item.*` with `entityTypeId` 1–4 in new integrations.
