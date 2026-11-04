# Запуск и остановка процессов

| Класс | Назначение |
| --- | --- |
| `CBPDocument` | Запуск, остановка, удаление, внешние события, состояния, права |
| `CBPRuntime` | Единственный экземпляр исполнителя; ID процессов |
| `CBPWorkflowTemplateLoader` | Поиск и загрузка шаблонов |
| `CBPDocumentService` | Доступ к провайдеру документа его модуля |
| `CBPDocumentEventType` | `Create`, `Edit`, `Manual`, `Automation`, `Trigger`, … |

Нормализуй ID через `CBPHelper::parseDocumentId($documentId)` либо `CBPHelper::normalizeComplexDocumentId($documentId)` (при неверном ID возвращает `null`).

## Методы CBPDocument

```php
$documentId = ['crm', 'CCrmDocumentDeal', 'DEAL_' . $dealId]; // формат задаёт провайдер
$errors = [];

$canStart = \CBPDocument::canUserOperateDocument(
    \CBPCanUserOperateOperation::StartWorkflow,
    $userId,
    $documentId,
);

if (!$canStart) {
    throw new \RuntimeException('Нет права запуска процесса');
}

$workflowId = \CBPDocument::startWorkflow(
    $templateId,
    $documentId,
    [
        \CBPDocument::PARAM_TAGRET_USER => 'user_' . $userId, // написание константы в ядре
        \CBPDocument::PARAM_DOCUMENT_EVENT_TYPE => \CBPDocumentEventType::Manual,
    ] + $templateParameters,
    $errors,
);

\CBPDocument::autoStartWorkflows($documentType, \CBPDocumentEventType::Create, $documentId, [], $errors);
\CBPDocument::sendExternalEvent($workflowId, $eventName, $eventParameters, $errors);
\CBPDocument::terminateWorkflow($workflowId, $documentId, $errors, 'Cancelled');
$errors = \CBPDocument::killWorkflow($workflowId, true, $documentId); // ОПАСНО: удаляет экземпляр, задания и журнал
```

Также: `getTemplatesForStart($userId, $documentType, $documentId)`, `getDocumentStates($documentType, $documentId)`, `getDocumentState($documentId, $workflowId)`.

Передавай настоящего инициатора в `PARAM_TAGRET_USER`: при пилотной публикации от него зависит выбранная версия шаблона. Если `$canStart === false`, прекрати обработку до `startWorkflow()`; пример показывает вызовы API, а не готовую цепочку выполнения.

## Заранее созданный ID процесса (**Since bizproc 26.1050**)

Создай ID через `CBPRuntime::generateWorkflowId()` и передай в `CBPDocument::PARAM_PRE_GENERATED_WORKFLOW_ID`. Неверное значение заменяется молча, поэтому сохранённый ID разойдётся с фактическим. `CBPStateService::addWorkflow()` бросает `InvalidWorkflowId`. Проверяй ID запроса через `CBPRuntime::isValidWorkflowId($id)`. На старом модуле сохраняй ID, возвращённый запуском.

## Запуск через Starter (D7)

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

Сценарии: `onManual`, `onDocumentAdd`, `onDocumentUpdate`, `onEvent` (триггеры, `rules/triggers.md`), `onScript`, `onRest`. Фасад готовых запусков: `Bitrix\Bizproc\Public\Service\Workflow\StarterService`. AJAX: `Bitrix\Bizproc\Controller\Workflow\Starter`.

## Псевдодокумент WORKFLOW

`['bizproc', \Bitrix\Bizproc\Public\Entity\Document\Workflow::class, 'WORKFLOW']` — тип процессов без сущности. **Since bizproc 26.1200** операции `CreateWorkflow`, `CreateAutomation`, `WriteDocument` доступны только администраторам (`CBPWorkflowTemplateUser::isAdmin()`); просмотр и запуск остаются открытыми. На старом модуле проверяй административные права явно.

## События завершения

Legacy `bizproc:OnWorkflowComplete($workflowId, $status)` срабатывает при завершении и остановке (`terminateWorkflow()`, `killWorkflow($id, true)`; `$status` = `CBPWorkflowStatus::Terminated`). Успешный `killWorkflow()` также отправляет D7 `bizproc:onAfterWorkflowKill` с параметром `ID`. `killWorkflow($id, false)` (агент зависших пауз, `rules/runtime-ops.md`) отправляет только D7-событие. Для учёта всех окончаний подпишись на оба (`bitrix-events`).
