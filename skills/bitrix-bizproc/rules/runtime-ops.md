# Runtime operations

## Bizproc vs agents vs Messenger

| Mechanism | Use when |
| --- | --- |
| Bizproc | Human tasks, approvals, document state, branching by fields |
| `CAgent` | Periodic maintenance without a document |
| `addBackgroundJob` | Short work after the response, same hit |
| Messenger | Reliable async jobs with retries (`messenger:consume`) |

Details: `bitrix-background-jobs`. An activity that needs long work sends a Messenger message and waits for an external event (`rules/custom-activities.md`). Bizproc itself schedules delays (`CBPDelayActivity`, `SchedulerService`) and queued starts (`WorkflowStartReceiver`); that is internal runtime, not a pattern to copy for approvals.

## Stuck paused workflows are deleted (**Since bizproc 26.1100**)

A daily agent `Bitrix\Bizproc\Infrastructure\Agent\ClearStuckPauseWorkflowAgent` kills workflows paused in Delay/RobotDelay/WaitWorkDay longer than option `bizproc:clear_stuck_pause_grace_days` (default 7, min 1) when nothing will wake them. It calls `killWorkflow($id, false)`: `onAfterWorkflowKill` fires, `OnWorkflowComplete` does not. Custom activities are not touched.

- Disable: `ClearStuckPauseWorkflowAgent::unregister()` (sets `clear_stuck_pause_agent_active=N`); enable: `::register()`.
- Or raise the grace days: `Option::set('bizproc', 'clear_stuck_pause_grace_days', '30')`.
- Audit: logger `bizproc.workflow.stuckPause` (`bitrix-logger`).

## Faults and tracking

- **Since bizproc 26.1100** a failing `HandleFault` is retried at most 3 times, then the workflow is terminated. Handle errors inside `execute()`.
- **Since bizproc 26.1400** nodes skipped by the engine write `b_bp_tracking` rows with `TYPE = CBPTrackingType::SkipActivity` (14), reason in `ACTION_NOTE`, no Close row. Reports on `b_bp_tracking` must handle type 14.

## Storage items no longer expire (**Since bizproc 26.1200**)

The cleanup agent, `StorageCleanupService`, `StorageSizeCleanupService` and option `storage_items_cleanup_days` were removed (calls → Error). Prune with your own agent:

```php
(new \Bitrix\Bizproc\Public\Command\StorageItem\DeleteStorageItemCommand($ids))->run(); // int|int[]
```
