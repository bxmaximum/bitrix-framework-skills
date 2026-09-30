# Reacting to CRM changes

UI, REST (`crm.item.*`, `crm.deal.*`), legacy `CCrmDeal::Update()` and your code all run the same Factory operations, so these events fire for every source. Register handlers on module install (`bitrix-events`): array events via `registerEventHandlerCompatible()`, `Main\Event` ones via `registerEventHandler()`.

| Entity | Events (module `crm`) | Handler gets |
| --- | --- | --- |
| Lead, Deal, Contact, Company, Quote | `OnBeforeCrm{Entity}Add`, `OnBeforeCrm{Entity}Update`, `OnAfterCrm{Entity}Add`, `OnAfterCrm{Entity}Update` | `array &$fields` |
| same | `OnBeforeCrm{Entity}Delete`, `OnAfterCrm{Entity}Delete` | `$id` |
| Deal, Lead, Quote | `OnAfterCrm{Entity}ProductRowsSave` (deal also `OnBefore…`) | `$id, array $rows` |
| Smart processes, smart invoices/documents | `onCrmDynamicItemAdd`, `onCrmDynamicItemUpdate`, `onCrmDynamicItemDelete`, plus `…_{entityTypeId}` names | `Main\Event`: `item`, `id` |
| Timeline comment | `OnAfterCrmTimelineCommentAdd`, `…Update`, `…Delete` | `Main\Event`: `ID` |
| Activity | `OnActivityAdd`, `OnActivityUpdate` / `OnActivityDelete` | `$id, array &$fields` / `$id` |

```php
final class DealHandler
{
    private static bool $busy = false;

    public static function onBeforeUpdate(array &$fields): bool
    {
        if ((float)($fields['OPPORTUNITY'] ?? 0) < 0) {
            $fields['RESULT_MESSAGE'] = 'Amount must not be negative';
            return false; // cancels; message goes to the operation Result
        }
        return true;
    }

    public static function onAfterUpdate(array &$fields): void
    {
        if (self::$busy) {
            return; // our own update below re-enters this handler
        }
        self::$busy = true;
        try {
            // resolve a service and do the work, or enqueue it
        } finally {
            self::$busy = false;
        }
    }
}
```

## Pitfalls

- `$fields` is the whole item in legacy keys (`getCompatibleData()`), not just changed keys: `isset($fields['STAGE_ID'])` is always true. Detect changes in an operation action (`getItemBeforeSave()`).
- `OnBefore*` handlers run after field processing and required checks: values written into `$fields` are saved unchecked.
- Smart processes have no before-event, and `onCrmDynamicItemUpdate` carries the saved item without previous values. Validation or diffs → factory override.
- Updating the same item from its own after-handler recurses (handlers and robots run again): guard with a flag.
- Robots run after the events; a robot that changes fields starts another update.
- HTTP calls, mail and other slow work: enqueue (`bitrix-background-jobs`).

## Factory override (all write sources)

The container and factories are ServiceLocator services. Global `services` win over crm's own: `registerByModuleSettings()` skips codes already registered.

```php
// global .settings.php (`bitrix-settings`), section 'services' => ['value' => [...]]
'crm.service.factory.deal' => ['className' => \Vendor\Crm\DealFactory::class],
'crm.service.container' => ['className' => \Vendor\Crm\Container::class], // smart processes
```

```php
use Bitrix\Crm\Item;
use Bitrix\Crm\Service\Context;
use Bitrix\Crm\Service\Operation;

final class DealFactory extends \Bitrix\Crm\Service\Factory\Deal
{
    public function getUpdateOperation(Item $item, ?Context $context = null): Operation\Update
    {
        $operation = parent::getUpdateOperation($item, $context);
        $operation->addAction(Operation::ACTION_BEFORE_SAVE, new RequireComment());

        return $operation;
    }
}

final class Container extends \Bitrix\Crm\Service\Container
{
    // subclass of Factory\Dynamic; branch on $this->getEntityTypeId() inside
    protected static $dynamicFactoriesClassName = DynamicFactory::class;
}
```

- The classes must autoload before your module is included (composer autoload or `Loader::registerNamespace()` in `init.php`): crm instantiates them lazily.
- Same for `crm.service.factory.lead|contact|company|quote`. Smart invoices/documents keep their own factory classes.
- Always call `parent::`; protected internals change between crm updates, retest after each.

## Automation

- Robots and triggers start from operations when `$factory->isAutomationEnabled()` and the operation did not `disableAutomation()`.
- Custom robot = bizproc activity (`bitrix-bizproc`).
- The trigger list is hard-coded (`Automation\Factory::getTriggerRegistry()`); a module cannot add trigger classes. From code, fire the "Webhook" trigger by its code (needs `rest`, see `WebHookTrigger::isEnabled()`):

```php
\Bitrix\Crm\Automation\Trigger\WebHookTrigger::execute(
    [['OWNER_TYPE_ID' => \CCrmOwnerType::Deal, 'OWNER_ID' => $dealId]],
    ['code' => 'paid'],
);
```
