# Custom activities

Search order (`Bitrix\Bizproc\Runtime\ActivitySearcher\Searcher`): `/local/activities`, `/local/activities/custom`, `/bitrix/activities/custom`, `/bitrix/activities/bitrix`, `/bitrix/modules/bizproc/activities`. Discovery is a folder scan; nothing goes into `.settings.php`.

```
/local/activities/vendorapprove/
├── .description.php     # ActivityDescription
├── vendorapprove.php    # class CBPVendorApprove extends CBPActivity
├── properties_dialog.php  # optional designer form
└── lang/ru/*.php
```

## .description.php

Pattern of `bizproc/install/activities/bitrix/delayactivity/.description.php`:

```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) { die(); }

use Bitrix\Bizproc\Activity\ActivityDescription;
use Bitrix\Bizproc\Activity\Enum\ActivityType;
use Bitrix\Main\Localization\Loc;

$arActivityDescription = (new ActivityDescription(
    name: Loc::getMessage('VENDOR_APPROVE_NAME'),
    description: Loc::getMessage('VENDOR_APPROVE_DESCR'),
    type: [ActivityType::ACTIVITY->value],
))
    ->setClass('VendorApprove') // class name without "CBP"
    ->setCategory(['ID' => 'other'])
    ->toArray();
```

Older bizproc without `ActivityDescription`: a plain array with the same keys (`NAME`, `DESCRIPTION`, `TYPE` = `'activity'`, `CLASS`, `CATEGORY`); the current searcher still accepts it.

- A `.description.php` that throws is skipped silently: the activity just disappears. The warning goes to logger `bizproc.runtime.activity_searcher` (enable it in `loggers`, `bitrix-logger`). **Since bizproc 26.1100**.
- `->setFilter(['MIN_API_VERSION' => 4])` hides the activity on runtimes older than API 4 (`CBPRuntime::ACTIVITY_API_VERSION`, 4 since bizproc 26.1100). Use it when the activity needs 26.1100+ APIs.
- The designer AI derives a description from the properties map. Opt out: `->setAiAutoDescription(false)` (array key `AI_AUTO_DESCRIPTION` = `N`); own text: `->setAiDescription('…')`.

## Class

```php
class CBPVendorApprove extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = ['Title' => '', 'Comment' => ''];
    }

    public function execute()
    {
        $documentId = $this->getDocumentId();
        $comment = (string)$this->Comment; // properties via magic __get
        // short work only; long jobs → Messenger + wait for an external event
        return CBPActivityExecutionStatus::Closed;
    }
}
```

- Validation for the designer: `public static function ValidateProperties($arTestProperties = [], ?CBPWorkflowTemplateUser $user = null)` → error list merged with `parent::`.
- Handle errors inside `execute()`. **Since bizproc 26.1100** a `HandleFault` that throws 3 times terminates the workflow.
- Overriding a kernel activity: keep the parent's visibility and signature. **Since bizproc 26.1100** `CBPMailActivity::getPropertiesMap(array $documentType, array $context = []): array` is `public static`; a `protected` override is a Fatal.

## Reserved member names (Fatal at class load)

`CBPActivity` uses trait `Bitrix\Bizproc\Public\Activity\Mixins\ChildFlowTraversal` (**Since bizproc 26.1400**; the two types methods since 26.1200). A same-named method with another signature or static-ness, or an untyped same-named property, is a Fatal. Don't declare in custom activities:

- Methods: `OnEvent`, `getActivityCode`, `getRawPropertyValue`, `markSkippedByEngine`, `getPropertiesTypes`, `getVariablesTypes`, `close`, `startChildFlow`, `getStartActivityNames`, `onDeadEndReached`, `executeByNames`, `executeActivity`, `getInputNames`, `getOutputNames`, `getAuxNames`, `extractActivityNameFromLink`, `createInputName`, `createOutputName`.
- Properties: `$activityQueue`, `$pendingQueue`, `$childFlowStarted`, `$childContainerEnabled`.

Prefix your helpers (`vendorLoadData()`).

Subclasses of `Bitrix\Bizproc\Public\Activity\Structure\FlowDirectedActivity` must override `getStartActivityNames(): array` and `onDeadEndReached(\CBPActivity $lastActivity): array` (return `[]` or `[static::createInputName($name, 0)]`). **Since bizproc 26.1400** the constructor throws `NotImplementedException` otherwise; in 26.1100–26.1300 they were abstract, and before 26.1100 `onDeadEndReached()` returned `void`.

## Waiting for another module's event (**Since bizproc 26.1050**)

```php
class CBPVendorWaitSigned extends CBPActivity implements IBPActivityExternalEventListener
{
    use \Bitrix\Bizproc\Activity\Mixins\ExternalEventSubscriptionTrait;

    private const EVENTS = ['OnDocumentSigned'];

    public function execute()
    {
        // hash = first positional string argument the event will carry
        $this->subscribeOnExternalEvents('vendor.sign', self::EVENTS, (string)$this->Comment, 86400);

        return CBPActivityExecutionStatus::Executing;
    }

    public function onExternalEvent($arEventParameters = [])
    {
        if ($this->isExternalEventIrrelevant($arEventParameters, self::EVENTS)) {
            return;
        }
        $this->unsubscribeFromExternalEvents('vendor.sign', self::EVENTS);
        if ($this->isExternalEventTimeout($arEventParameters)) {
            $this->WriteToTrackingService('Timed out');
        }
        $this->workflow->closeActivity($this);
    }
}
```

Fire the event with `new \Bitrix\Main\Event('vendor.sign', 'OnDocumentSigned', [$hash, …])` → `send()`, or legacy `ExecuteModuleEventEx`.
