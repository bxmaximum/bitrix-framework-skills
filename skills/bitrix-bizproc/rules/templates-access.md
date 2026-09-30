# Templates: rights, import, services

## Rights on Nodes templates (**Since bizproc 26.1200**)

`TYPE=nodes` templates use `Main\Access` permissions: create, edit, publish (includes edit), delete, configure rights. Default roles are bound to nobody, so non-admins see nothing until an admin assigns roles (saving roles needs option `designer_v2=Y`). Classic templates still use the document type operation `CreateWorkflow`.

```php
$access = new \Bitrix\Bizproc\Public\Service\TemplateAccessService();
$access->canAccessTemplates($userId);
$access->canCreate($userId);
$access->canEdit($templateId, $userId);   // also canPublish(), canDelete()

// Row filter for your own template lists: [] = all, ['=ID' => -1] = none
$filter = (new \Bitrix\Bizproc\Public\Provider\TemplateAccessProvider())->getEntityFilter($userId);
```

REST `bizproc.workflow.template.list` no longer returns an access error to non-admins (**Since bizproc 26.1200**): they get their editable/publishable Nodes templates or an empty list. Don't infer admin rights from it.

## Custom designer controllers (**Since bizprocdesigner 26.600**)

Never trust `documentType` from the request next to `templateId`: take it from the template.

```php
use Bitrix\Bizproc\Workflow\Template\Entity\WorkflowTemplateTable;

$template = WorkflowTemplateTable::getById($templateId)->fetchObject();
$documentType = $template?->getDocumentComplexType();
try {
    $allowed = $documentType !== null && \CBPDocument::canUserOperateDocumentType(
        \CBPCanUserOperateOperation::CreateWorkflow,
        $userId,
        $documentType,
    );
} catch (\Throwable) {
    $allowed = false;
}
// one ACCESS_DENIED for "missing" and "foreign" — no existence oracle
```

Controller filters and errors: `bitrix-controllers`.

## Importing templates

Custom importers call `\CBPWorkflowTemplateLoader::checkImportedTemplateActivities($template, $user)` → `Main\Result` before saving (**Since bizproc 26.1100**). Without it a non-admin could import `CBPCodeActivity` (arbitrary PHP).

## WorkflowTemplateService

`Bitrix\Bizproc\Api\Service\WorkflowTemplateService` gained optional dependencies twice (26.1300, 26.1400): the constants-policy argument moved from 4th to 10th. Construct it with no arguments; positional arguments give a TypeError.
