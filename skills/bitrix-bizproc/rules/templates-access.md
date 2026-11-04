# Шаблоны: права, импорт и сервисы

## Права узловых шаблонов (**Since bizproc 26.1200**)

Шаблоны `TYPE=nodes` используют права `Main\Access`: создание, изменение, публикация (включает изменение), удаление, настройка прав. Роли по умолчанию никому не назначены: обычный пользователь ничего не увидит до назначения администратором. Для сохранения ролей нужен включённый новый дизайнер. Классические шаблоны используют `CreateWorkflow` типа документа. На старом модуле проверяй права через API документа.

```php
$access = new \Bitrix\Bizproc\Public\Service\TemplateAccessService();
$access->canAccessTemplates($userId);
$access->canCreate($userId);
$access->canEdit($templateId, $userId);   // также canPublish(), canDelete()

// Фильтр списка: [] — всё, ['=ID' => -1] — ничего.
$filter = (new \Bitrix\Bizproc\Public\Provider\TemplateAccessProvider())->getEntityFilter($userId);
```

**Since bizproc 26.1200** REST `bizproc.workflow.template.list` возвращает обычному пользователю доступные для редактирования/публикации узловые шаблоны либо пустой список. Это не признак административных прав. На старом модуле такой вызов может вернуть отказ.

## Свои контроллеры дизайнера (**Since bizprocdesigner 26.600**)

Бери `documentType` из шаблона, а не из запроса вместе с `templateId`. На старой версии применяй ту же проверку в собственном обработчике.

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
// Одинаковый ACCESS_DENIED для отсутствующего и чужого шаблона скрывает его существование.
```

Фильтры и ошибки контроллера: `bitrix-controllers`.

## Импорт шаблонов

**Since bizproc 26.1100** перед сохранением вызывай `\CBPWorkflowTemplateLoader::checkImportedTemplateActivities($template, $user)` и проверяй `Main\Result`. Иначе обычный пользователь может импортировать `CBPCodeActivity` с произвольным PHP. На старой версии разрешай импорт только доверенному администратору либо проверяй действия собственным белым списком.

## Сервис WorkflowTemplateService

`Bitrix\Bizproc\Api\Service\WorkflowTemplateService` дважды получил необязательные зависимости (26.1300, 26.1400): аргумент политики констант переместился с позиции 4 на 10. Создавай сервис без аргументов; старые позиционные вызовы могут дать `TypeError`.


## Фильтр своих шаблонов и новый дизайнер

**Since bizproc 26.1500** при включённой возможности своих шаблонов `TemplateAccessProvider::getEntityFilter()` может вернуть `ConditionTree` под `SCOPE_CONDITION_KEY`. Вынеси условие из массива и передай в `Query::where()`. Не теряй его при объединении фильтров: иначе расширится доступ. На старом модуле достаточно обычного массива провайдера.

```php
$provider = new \Bitrix\Bizproc\Public\Provider\TemplateAccessProvider();
$filter = $provider->getEntityFilter($userId);
$scope = $filter[$provider::SCOPE_CONDITION_KEY] ?? null;
unset($filter[$provider::SCOPE_CONDITION_KEY]);
$query = \Bitrix\Bizproc\Workflow\Template\Entity\WorkflowTemplateTable::query()
    ->setSelect(['ID', 'NAME'])->setFilter($filter);
if ($scope !== null) {
    $query->where($scope);
}
$templates = $query->fetchAll();
```

**Since bizproc 26.1700** новый дизайнер включён по умолчанию через `Bitrix\Bizproc\Public\Feature\Designer\DesignerV2Flag`. Опция `designer_v2` синхронизируется с флагом; одной записи опции недостаточно. Для осознанного возврата старого дизайнера используй `\Bitrix\Main\Config\Feature::disable(\Bitrix\Bizproc\Public\Feature\Designer\DesignerV2Flag::class)`. На старом модуле используется его опция дизайнера.
