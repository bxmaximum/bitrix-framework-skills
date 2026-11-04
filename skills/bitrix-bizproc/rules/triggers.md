# Свои триггеры узлового дизайнера

Триггер запускает шаблоны `TYPE=nodes` по событию модуля. Его каталог устроен как каталог действия (`rules/custom-activities.md`). Образцы ядра: `bizproc/install/activities/bitrix/createdocumenttrigger/`, `fieldchangedtrigger/`.

```php
class CBPVendorOrderPaidTrigger extends \Bitrix\Bizproc\Activity\BaseTrigger
{
    public function execute(): int
    {
        return CBPActivityExecutionStatus::Closed;
    }

    public static function getPropertiesMap(array $documentType, array $context = []): array
    {
        return []; // поля дизайнера; значения поступают в checkApplyRules()
    }

    public function checkApplyRules(
        array $rules,
        \Bitrix\Bizproc\Activity\Trigger\TriggerParameters $parameters,
    ): \Bitrix\Bizproc\Result {
        return \Bitrix\Bizproc\Result::createOk(); // ошибочный Result отменяет этот запуск
    }

    protected static function getModuleId(): ?string
    {
        return 'vendor.shop';
    }
}
```

- В `.description.php` триггера задавай `type: []`. **Since bizproc 26.1400** тип определяет `CBPRuntime::getRuntime()->isTriggerActivity($code)`, а не суффикс `Trigger`; на старом модуле сверяй его механизм регистрации.
- Если триггер относится к документу, переопредели `getDocumentComplexType(): array` (по умолчанию псевдотип `WORKFLOW`) и `getSection()`. **Since bizproc 26.1400** внешний код читает тип через `getEventDocumentComplexType()`; на старом модуле используй доступный API его класса.
- Сохранение значений дизайнера: `protected static applyPropertiesDialogValues()` (**Since bizproc 26.1200**). На старом модуле используй штатную форму соответствующей версии.

## Отправка события

```php
use Bitrix\Bizproc\Starter\Dto\ContextDto;
use Bitrix\Bizproc\Starter\Dto\DocumentDto;
use Bitrix\Bizproc\Starter\Enum\Scenario;
use Bitrix\Bizproc\Starter\Starter;

$context = new ContextDto('vendor.shop');
$documents = [new DocumentDto($documentId)];

$result = Starter::getByScenario(Scenario::onEvent)
    ->setContext($context)
    ->addEvent('VendorOrderPaidTrigger', $documents, ['SUM' => $sum])
    ->start();
```

`addEvent(string $code, array $documents = [], array $parameters = [])`: `$code` — имя класса триггера без `CBP`. Без документов запускаются процессы без документа. Образец ядра: `bizproc/lib/Integration/Intranet/EventHandler.php`.

## Замена старого триггера

Модуль документа сопоставляет старые типы с новыми через `ModuleSettings::getTriggerUpgradeMap(): array<string, TriggerUpgradeDto>` и `new \Bitrix\Bizproc\Starter\Dto\TriggerUpgradeDto($newTriggerType, $extraProperties)` (**Since bizproc 26.1400**). На 26.1200 используй прежний `getInterchangeableTriggerPairs()`; в 26.1400 он удалён и не читается. <!-- verify:negative -->
