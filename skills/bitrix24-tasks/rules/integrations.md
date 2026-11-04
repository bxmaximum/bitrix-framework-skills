# Интеграция задач с БП и REST


**Since tasks 26.750** `Integration\Bizproc\Document\Task::updateDocument()` игнорирует `STATUS`, `STAGE_ID`, `ADD_IN_REPORT`. Статус меняй через `Task::updateDocumentStatus($taskId, $status, $modifiedById)` либо отдельный тип документа `TaskStatusUpdate`. Ошибки записи/прав могут бросать `Main\SystemException`, а не возвращать `false`. На старом модуле сохраняй поддерживаемый им путь и проверяй результат.

**Since tasks 26.700** классический REST `tasks.task.get/list` выдаёт `DURATION_PLAN` в единицах `DURATION_TYPE`: минуты превращаются в часы, часы округляются. Дни учитывают рабочий день при `MATCH_WORK_TIME=Y`, иначе 86400 секунд. Не интерпретируй число как секунды без проверки единицы. В старом ответе используй старый контракт. Это не меняет Unix timestamp дат V2.

**Since tasks 26.800** `tasks.template.update` игнорирует `params.USER_ID` и использует текущего пользователя. На старом модуле также не позволяй внешнему запросу подменять инициатора.
