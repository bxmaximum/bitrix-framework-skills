# Совместимость старого API и REST

## Старый API и D7

В проверенной crm 26.1900 `Add()`/`Update()`/`Delete()` классов `CCrmLead`/`CCrmDeal`/`CCrmContact`/`CCrmCompany` делегируют операциям Factory через `Crm\Entity\Compatibility\Adapter` (`*Settings::isFactoryEnabled()` возвращает `true`). Адаптер не запускает роботов и БП (`setRunAutomation(false)`, `setRunBizProc(false)`): старые вызывающие места запускают их отдельно. Ошибки возвращаются строкой `LAST_ERROR`. При переходе на D7 роботы начнут запускаться; сохрани прежнюю логику через `disableAutomation()`, если она исключала автоматизацию. Legacy оставляй только в нетронутом коде; на старом модуле проверь его собственный путь записи.

| Старый API для совместимости | D7 |
| --- | --- |
| `(new \CCrmDeal(false))->Add($fields)` | `$factory->getAddOperation($item, $context)` + `disableCheckAccess()` |
| `->Update($id, $fields)`, `->Delete($id)` | `getUpdateOperation()`, `getDeleteOperation()` |
| `\CCrmDeal::GetListEx()`, `GetByID()` | `getItems()`, `getItem()`, `getItemsFilteredByPermissions()` |
| `\CCrmFieldMulti::GetList()`, `SetFields()` | `Item::getFm()`/`setFm()` |
| `\CCrmProductRow::SaveRows()` | `Item::setProductRowsFromArrays()` + операция изменения |
| `\CCrmPerms`, `\CCrmDeal::CheckReadPermission()`, `\CCrmAuthorizationHelper` | `getUserPermissions()->item()`/`entityType()` |
| `\CCrmActivity::Add()` для запланированных дел | `Activity\Entity\ToDo` |
| `\CCrmStatus` для стадий | чтение: `$factory->getStages()`; запись: пока `CCrmStatus` |
| `\CCrmInvoice` (тип 5) | смарт-счета (31) через фабрику |
| `DealTable`/`LeadTable`/`ContactTable`/`CompanyTable::add/update/delete` | не использовать для записи; допустимо чтение |

Legacy-массивы используют имена столбцов (`STATUS_ID` лида, `DATE_CREATE`, `CREATED_BY_ID`); D7 — `Item::FIELD_NAME_*` (`STAGE_ID`, `CREATED_TIME`, `CREATED_BY`). Преобразование: `$item->getCompatibleData()` / `setFromCompatibleData()`.

## Соответствие D7 и REST

В облаке доступа к PHP нет: используй REST (`bitrix24-rest-api`). REST-обработчики выполняют операции с контекстом REST.

| D7 коробки | REST |
| --- | --- |
| `getItems()`, `getItem()` | `crm.item.list`, `crm.item.get` (`entityTypeId`) |
| Операции добавления/изменения/удаления | `crm.item.add`, `crm.item.update`, `crm.item.delete` |
| `getFieldsInfo()` | `crm.item.fields` |
| `getTypeByEntityTypeId()`, ORM-объект типа | `crm.type.getByEntityTypeId`, `crm.type.list`, `crm.type.add` |
| `getCategories()`, `createCategory()` | `crm.category.list`, `crm.category.add` (`entityTypeId`) |
| `getStages()`, `CCrmStatus` | `crm.status.list`, `crm.status.add` (`ENTITY_ID` = `getStagesEntityId()`) |
| `CommentEntry::create()` | `crm.timeline.comment.add` |
| `Activity\Entity\ToDo` | `crm.activity.todo.add` |
| `CUserTypeEntity` + `getUserFieldEntityId()` | `userfieldconfig.add` (`moduleId: crm`) |
| `onCrmDynamicItem*`, `OnAfterCrm*` | REST-события `onCrmDynamicItemAdd`…, `onCrmDealAdd`… |
| `WebHookTrigger::execute()` | `crm.automation.trigger` (вебхук), `crm.automation.trigger.execute` (приложение) |

- REST использует camelCase (`stageId`, `assignedById`, `ufCrm5_…`), D7 — UPPER_SNAKE.
- Legacy REST `crm.deal.*`, `crm.lead.*` вызывает старые классы выше. В новых интеграциях предпочитай `crm.item.*` с `entityTypeId` 1–4.
