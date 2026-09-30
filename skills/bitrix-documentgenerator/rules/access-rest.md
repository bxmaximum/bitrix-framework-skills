# Access rights and REST

## Module rights

```php
$permissions = \Bitrix\DocumentGenerator\Driver::getInstance()->getUserPermissions($userId);   // cached per user
if (!$permissions->canCreateDocumentOnTemplate($templateId))
{
    return $result->addError(new \Bitrix\Main\Error('Access denied'));   // $result: \Bitrix\Main\Result
}
```

`UserPermissions` methods: `canViewDocuments()`, `canModifyDocuments()`, `canModifyDocument($documentIdOrDocument)`, `canModifyTemplates()`, `canModifyTemplate(int $templateId)`, `canCreateDocumentOnTemplate(int $templateId)`, `canModifySettings()`, `getFilterForTemplateList()` (ORM filter for `TemplateTable`).

- Roles (`Model\RoleTable`, `RolePermissionTable`, `RoleAccessTable`) are edited in UI `bitrix:documentgenerator.settings.perms`; defaults ADMIN (admins) and MANAGER (company root department) are created on install.
- Admin status is evaluated only for the current user; for other ids roles apply.
- Template visibility per user: `TemplateUserTable` access codes; list templates for a provider with `Model\TemplateTable::getListByClassName($providerClass, $userId, $value)`.

## Three layers — check all

1. Module right (above) — may the user create/see documents at all.
2. Template access codes — may the user use this template.
3. Data access — `$document->hasAccess($userId)` → your provider's `hasAccess()`. `Template::loadById()` and `Document::createByTemplate()` check nothing.

User id `0` passes every data check (agents, CLI): set a real user with `setUserId()` when generating on behalf of someone.

## Controllers and REST

- Module controllers are exposed under alias `api` with REST integration: AJAX `documentgenerator.api.document.*`, `documentgenerator.api.template.*` (e.g. `BX.ajax.runAction('documentgenerator.api.document.getfile', …)`).
- Public REST (scope `documentgenerator`): `documentgenerator.template.{add,update,delete,get,list,getfields}`, `documentgenerator.document.{add,update,delete,get,list,getfields,enablepublicurl}`, `documentgenerator.numerator.*`, `documentgenerator.region.*`, `documentgenerator.role.{…,fillaccesses}`. Details: `bitrix24-rest-api`.
- Templates added through REST are forced to module `rest` with the `DataProvider\Rest` provider — REST apps pass all values in `document.add` (`values`), they can't use a PHP provider. REST can't reach templates/documents of other modules (`CheckScope`).
- CRM documents have their own REST: `crm.documentgenerator.{template,document,numerator}.*` (`bitrix24-crm`).

## UI components

`bitrix:documentgenerator.templates` (list/upload), `.documents` (grid), `.placeholders` (field catalog), `.settings.perms` (roles), `.config`, `.region`, `.templates.default`, `.feature`, `.view` (public view by id + hash). They are product UI — open them in a slider rather than rebuilding.
