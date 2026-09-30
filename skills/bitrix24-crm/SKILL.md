---
name: bitrix24-crm
description: 'D7 CRM in Bitrix24 on-premise: Container, Factory, Item, Operations, smart processes (dynamic types), stages, permissions, timeline, CRM events. Use for server-side deal, lead, contact, company or smart process code in /local.'
---

# Bitrix24 CRM (server side)

Scope: Bitrix24 on-premise · Verified: crm 26.800.0, main 26.800.0

PHP running inside the portal: `/local/` modules, handlers, agents. Calling CRM over REST: `bitrix24-rest-api`.

| Task | Read |
| --- | --- |
| Container, Factory, reading items, field names, phones/e-mails | `rules/factory-items.md` |
| Add/update/delete, Context, checks, custom actions | `rules/operations.md` |
| Smart processes, funnels, stages, smart invoices | `rules/smart-processes.md` |
| Rights checks, permission-filtered lists | `rules/permissions.md` |
| Reacting to changes, factory override, robots, triggers | `rules/events-automation.md` |
| Timeline comments, to-dos, activities | `rules/timeline-activities.md` |
| Fields collection, user fields | `rules/fields-uf.md` |
| `CCrmDeal`/`CCrmLead`/… replacements, D7 ↔ REST map | `rules/legacy-rest.md` |

## Invariants

- `Loader::includeModule('crm')` first; get services from `Container::getInstance()`.
- Write only through Factory operations: `getAddOperation()`/`getUpdateOperation()`/`getDeleteOperation()` → `launch()`. Never `DealTable::add()`, `$factory->getDataClass()::update()`, `Item::save()`/`Item::delete()`: they skip rights, required fields, events, timeline, search index and robots.
- Use `Item::FIELD_NAME_*` names; the factory maps them to entity columns (lead `STAGE_ID` → `STATUS_ID`).
- Dynamic `entityTypeId`, funnel and stage IDs differ per portal: resolve by code or semantics at runtime, never hardcode.
- Agents, CLI and queues have no user: pass a `Context` with a user ID, or disable the access check on purpose.
- Check `isSuccess()` of every `launch()` and pass its errors up.
