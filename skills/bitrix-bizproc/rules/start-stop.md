# Start and stop workflows

| Class | Role |
| --- | --- |
| `CBPDocument` | Start, terminate, kill, external events, states, rights |
| `CBPRuntime` | Runtime singleton; workflow ids |
| `CBPWorkflowTemplateLoader` | Template search and load |
| `CBPDocumentService` | Bridge to the document provider of the owner module |
| `CBPDocumentEventType` | `Create`, `Edit`, `Manual`, `Automation`, `Trigger`, … |

Normalize ids with `CBPHelper::parseDocumentId($documentId)` or `CBPHelper::normalizeComplexDocumentId($documentId)` (returns `null` for a bad id).

## CBPDocument

```php
$documentId = ['crm', 'CCrmDocumentDeal', 'DEAL_' . $dealId]; // shape is provider-specific
$errors = [];

$canStart = \CBPDocument::canUserOperateDocument(
    \CBPCanUserOperateOperation::StartWorkflow,
    $userId,
    $documentId,
);

$workflowId = \CBPDocument::startWorkflow(
    $templateId,
    $documentId,
    [
        \CBPDocument::PARAM_TAGRET_USER => 'user_' . $userId, // kernel spelling
        \CBPDocument::PARAM_DOCUMENT_EVENT_TYPE => \CBPDocumentEventType::Manual,
    ] + $templateParameters,
    $errors,
);

\CBPDocument::autoStartWorkflows($documentType, \CBPDocumentEventType::Create, $documentId, [], $errors);
\CBPDocument::sendExternalEvent($workflowId, $eventName, $eventParameters, $errors);
\CBPDocument::terminateWorkflow($workflowId, $documentId, $errors, 'Cancelled');
$errors = \CBPDocument::killWorkflow($workflowId, true, $documentId); // DANGER: deletes instance, tasks, tracking
```

Also: `getTemplatesForStart($userId, $documentType, $documentId)`, `getDocumentStates($documentType, $documentId)`, `getDocumentState($documentId, $workflowId)`.

Always pass the real initiator in `PARAM_TAGRET_USER`: with pilot publication enabled it selects which template version runs.

## Pre-generated workflow id (**Since bizproc 26.1050**)

Create it with `CBPRuntime::generateWorkflowId()` and pass it as `CBPDocument::PARAM_PRE_GENERATED_WORKFLOW_ID`. An invalid value is silently replaced (your stored id no longer matches); `CBPStateService::addWorkflow()` throws `InvalidWorkflowId`. Validate ids from requests with `CBPRuntime::isValidWorkflowId($id)`.

## Starter (D7)

```php
use Bitrix\Bizproc\Starter\Dto\ContextDto;
use Bitrix\Bizproc\Starter\Dto\DocumentDto;
use Bitrix\Bizproc\Starter\Enum\Scenario;
use Bitrix\Bizproc\Starter\Starter;

$document = new DocumentDto($documentId);
$context = new ContextDto('vendor.module');

$result = Starter::getByScenario(Scenario::onManual)
    ->setDocument($document)
    ->setTemplateIds([$templateId])
    ->setContext($context)
    ->setUser($userId)
    ->setParameters($templateParameters)
    ->start(); // StartResult
if ($result->isSuccess()) {
    $workflowIds = $result->getWorkflowIds();
}
```

Scenarios: `onManual`, `onDocumentAdd`, `onDocumentUpdate`, `onEvent` (triggers, `rules/triggers.md`), `onScript`, `onRest`. Facade with ready-made starters: `Bitrix\Bizproc\Public\Service\Workflow\StarterService`. AJAX: `Bitrix\Bizproc\Controller\Workflow\Starter`.

## WORKFLOW pseudo-document

`['bizproc', \Bitrix\Bizproc\Public\Entity\Document\Workflow::class, 'WORKFLOW']` is a document type for processes without an entity. **Since bizproc 26.1200** `CreateWorkflow`, `CreateAutomation` and `WriteDocument` on it pass only for admins (`CBPWorkflowTemplateUser::isAdmin()`); viewing and starting stay open.

## Completion events

`bizproc:OnWorkflowComplete($workflowId, $status)` (legacy) fires on completion and on termination (`terminateWorkflow()`, `killWorkflow($id, true)`; `$status` = `CBPWorkflowStatus::Terminated`). A successful `killWorkflow()` also fires D7 `bizproc:onAfterWorkflowKill` with param `ID`; `killWorkflow($id, false)` (the stuck-pause agent, `rules/runtime-ops.md`) fires only that one. Trackers that must see every end subscribe to both (`bitrix-events`).
