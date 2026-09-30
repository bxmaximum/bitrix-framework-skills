# Custom triggers (Nodes designer)

A trigger starts `TYPE=nodes` templates on an event of your module. It is an activity folder like any other (`rules/custom-activities.md`); kernel samples: `bizproc/install/activities/bitrix/createdocumenttrigger/`, `fieldchangedtrigger/`.

```php
class CBPVendorOrderPaidTrigger extends \Bitrix\Bizproc\Activity\BaseTrigger
{
    public function execute(): int
    {
        return CBPActivityExecutionStatus::Closed;
    }

    public static function getPropertiesMap(array $documentType, array $context = []): array
    {
        return []; // designer fields; values arrive in checkApplyRules()
    }

    public function checkApplyRules(
        array $rules,
        \Bitrix\Bizproc\Activity\Trigger\TriggerParameters $parameters,
    ): \Bitrix\Bizproc\Result {
        return \Bitrix\Bizproc\Result::createOk(); // or an error Result to skip this start
    }

    protected static function getModuleId(): ?string
    {
        return 'vendor.shop';
    }
}
```

- `.description.php` of a trigger uses `type: []`. Whether a code is a trigger is decided by `CBPRuntime::getRuntime()->isTriggerActivity($code)` (**Since bizproc 26.1400**), not by the `Trigger` suffix.
- Override `getDocumentComplexType(): array` (default: the `WORKFLOW` pseudo-type) and `getSection()` when the trigger belongs to a document type. External callers read it via `getEventDocumentComplexType()` (**Since bizproc 26.1400**).
- Hook for designer values: `protected static applyPropertiesDialogValues()` (**Since bizproc 26.1200**).

## Firing

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

`addEvent(string $code, array $documents = [], array $parameters = [])`: `$code` is the trigger class name without `CBP`; without documents the event starts processes with no document (kernel: `bizproc/lib/integration/intranet/eventhandler.php`).

## Retiring a trigger

A document module maps old trigger types to new ones in its `ModuleSettings::getTriggerUpgradeMap(): array<string, TriggerUpgradeDto>` with `new \Bitrix\Bizproc\Starter\Dto\TriggerUpgradeDto($newTriggerType, $extraProperties)` (**Since bizproc 26.1400**). `getInterchangeableTriggerPairs()` from 26.1200 was removed in 26.1400 and is no longer read.
