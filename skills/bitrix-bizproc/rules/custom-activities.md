# Свои действия

Порядок поиска (`Bitrix\Bizproc\Runtime\ActivitySearcher\Searcher`): `/local/activities`, `/local/activities/custom`, `/bitrix/activities/custom`, `/bitrix/activities/bitrix`, `/bitrix/modules/bizproc/activities`. Система сканирует каталоги; регистрация в `.settings.php` не нужна.

```
/local/activities/vendorapprove/
├── .description.php       # описание ActivityDescription
├── vendorapprove.php      # класс CBPVendorApprove extends CBPActivity
├── properties_dialog.php  # необязательная форма дизайнера
└── lang/ru/*.php
```

## Описание .description.php

Образец: `bizproc/install/activities/bitrix/delayactivity/.description.php`:

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
    ->setClass('VendorApprove') // имя класса без «CBP»
    ->setCategory(['ID' => 'other'])
    ->toArray();
```

В старом bizproc без `ActivityDescription` используй массив с ключами `NAME`, `DESCRIPTION`, `TYPE` = `'activity'`, `CLASS`, `CATEGORY`. Текущий поиск также принимает этот формат.

- При исключении в `.description.php` действие пропускается и исчезает из каталога. Предупреждение пишет логгер `bizproc.runtime.activity_searcher`; включи его в `loggers` (`bitrix-logger`). **Since bizproc 26.1100**; на старом модуле проверяй загрузку описания самостоятельно.
- `->setFilter(['MIN_API_VERSION' => 4])` скрывает действие на исполнителе до API 4 (`CBPRuntime::ACTIVITY_API_VERSION`, значение 4 с bizproc 26.1100). Используй для действий с API 26.1100+; на старом модуле не устанавливай такое действие.
- ИИ дизайнера строит описание по карте свойств. Отключение: `->setAiAutoDescription(false)` (`AI_AUTO_DESCRIPTION` = `N`); свой текст: `->setAiDescription('…')`.

## Класс

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
        $comment = (string)$this->Comment; // свойства доступны через __get
        // Только короткая работа; долгую передай Messenger и жди внешнее событие.
        return CBPActivityExecutionStatus::Closed;
    }
}
```

- Валидация дизайнера: `public static function ValidateProperties($arTestProperties = [], ?CBPWorkflowTemplateUser $user = null)`. Объединяй свои ошибки со списком из `parent::`.
- Обрабатывай ошибки внутри `execute()`. **Since bizproc 26.1100** три исключения из `HandleFault` завершают процесс; на старом модуле ограничивай повторы самостоятельно.
- При наследовании действия сохраняй видимость и сигнатуру родителя. **Since bizproc 26.1100** `CBPMailActivity::getPropertiesMap(array $documentType, array $context = []): array` — `public static`; переопределение как `protected` вызывает фатальную ошибку. На старом модуле сверяй его собственную сигнатуру.

## Зарезервированные члены: ошибка при загрузке класса

`CBPActivity` использует `Bitrix\Bizproc\Public\Activity\Mixins\ChildFlowTraversal` (**Since bizproc 26.1400**; методы двух карт типов появились в 26.1200). Одноимённый метод с другой сигнатурой/static или одноимённое нетипизированное свойство вызывают фатальную ошибку. Сохраняй префиксы помощников и на старом модуле. Не объявляй:

- Методы: `OnEvent`, `getActivityCode`, `getRawPropertyValue`, `markSkippedByEngine`, `getPropertiesTypes`, `getVariablesTypes`, `close`, `startChildFlow`, `getStartActivityNames`, `onDeadEndReached`, `executeByNames`, `executeActivity`, `getInputNames`, `getOutputNames`, `getAuxNames`, `extractActivityNameFromLink`, `createInputName`, `createOutputName`.
- Свойства: `$activityQueue`, `$pendingQueue`, `$childFlowStarted`, `$childContainerEnabled`.

Используй префикс помощников (`vendorLoadData()`).

Наследники `Bitrix\Bizproc\Public\Activity\Structure\FlowDirectedActivity` переопределяют `getStartActivityNames(): array` и `onDeadEndReached(\CBPActivity $lastActivity): array` (результат `[]` либо `[static::createInputName($name, 0)]`). **Since bizproc 26.1400** иначе конструктор бросает `NotImplementedException`; в 26.1100–26.1300 методы абстрактные, до 26.1100 `onDeadEndReached()` возвращал `void`. Для старого модуля сохраняй его сигнатуру.

## Ожидание события другого модуля (**Since bizproc 26.1050**)

```php
class CBPVendorWaitSigned extends CBPActivity implements IBPActivityExternalEventListener
{
    use \Bitrix\Bizproc\Activity\Mixins\ExternalEventSubscriptionTrait;

    private const EVENTS = ['OnDocumentSigned'];

    public function execute()
    {
        // hash — первый позиционный строковый аргумент будущего события.
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
            $this->WriteToTrackingService('Время ожидания истекло');
        }
        $this->workflow->closeActivity($this);
    }
}
```

Отправляй событие через `new \Bitrix\Main\Event('vendor.sign', 'OnDocumentSigned', [$hash, …])` и `send()` либо legacy `ExecuteModuleEventEx`. На модуле до 26.1050 реализуй подписку/отписку и таймаут через собственный обработчик.
