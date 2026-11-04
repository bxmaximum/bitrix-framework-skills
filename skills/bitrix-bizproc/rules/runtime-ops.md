# Эксплуатация процессов

## Выбор механизма

| Механизм | Назначение |
| --- | --- |
| Bizproc | Задания людям, согласования, состояние документа, ветвление по полям |
| `CAgent` | Периодическое обслуживание без документа |
| `addBackgroundJob` | Короткая работа после ответа в том же запросе |
| Messenger | Асинхронные задания с повторами (`messenger:consume`) |

Подробности: `bitrix-background-jobs`. Долгое действие отправляет сообщение Messenger и ждёт внешнее событие (`rules/custom-activities.md`). Собственные задержки bizproc (`CBPDelayActivity`, `SchedulerService`) и очередь запусков (`WorkflowStartReceiver`) — внутренние механизмы; не копируй их для реализации согласований.

## Удаление зависших пауз (**Since bizproc 26.1100**)

Ежедневный агент `Bitrix\Bizproc\Infrastructure\Agent\ClearStuckPauseWorkflowAgent` удаляет процессы, которые никто не разбудит и которые ждут в Delay/RobotDelay/WaitWorkDay дольше `bizproc:clear_stuck_pause_grace_days` (по умолчанию 7 дней, минимум 1). Вызов `killWorkflow($id, false)` отправляет `onAfterWorkflowKill`, но не `OnWorkflowComplete`. Свои действия не затрагиваются. На старом модуле организуй диагностику пауз самостоятельно.

- Отключить: `ClearStuckPauseWorkflowAgent::unregister()` (записывает `clear_stuck_pause_agent_active=N`); включить: `::register()`.
- Увеличить срок: `Option::set('bizproc', 'clear_stuck_pause_grace_days', '30')`.
- Аудит: логгер `bizproc.workflow.stuckPause` (`bitrix-logger`).

## Ошибки и журнал

- **Since bizproc 26.1100** после трёх исключений из `HandleFault` процесс завершается. Обрабатывай ошибки в `execute()`; на старом модуле ограничивай повторы самостоятельно.
- **Since bizproc 26.1400** пропущенные узлы пишутся в `b_bp_tracking` с `TYPE = CBPTrackingType::SkipActivity` (14), причиной в `ACTION_NOTE`, без строки Close. Учитывай тип 14 в отчётах; на старом модуле не ожидай таких записей.

## Хранилище больше не очищается по сроку (**Since bizproc 26.1200**)

Агент очистки, `StorageCleanupService`, `StorageSizeCleanupService` и опция `storage_items_cleanup_days` удалены; вызовы классов завершатся ошибкой. На старом модуле проверь его штатную очистку. На новом удаляй данные своим агентом: <!-- verify:negative -->

```php
(new \Bitrix\Bizproc\Public\Command\StorageItem\DeleteStorageItemCommand($ids))->run(); // int|int[]
```


## Пакетное выполнение заданий

**Since bizproc 26.1600** AJAX `bizproc.task.doCollection` принимает `taskIds` и `taskRequest` формы задания, только POST, до 50 заданий. Права проверяются для текущего пользователя. Ответ содержит `results` и `doneCount`; проверяй каждое задание (`done`, `not_found`, `access_denied`, `error`). Общей транзакции нет: общий успех запроса не означает выполнения всех заданий. На старом модуле выполняй задания по одному штатным обработчиком.

**Since bizproc 26.1700** общие файловые поля пакета запрещены (`TASK_FILE_FIELD_NOT_SHARED`). В одиночном задании произвольный ID существующего файла также не заменяет загрузку: допускаются уже сохранённое значение либо файл штатного загрузчика этого задания. Через REST передавай содержимое файла по контракту метода. На старом модуле самостоятельно проверяй доступ и происхождение файла.
