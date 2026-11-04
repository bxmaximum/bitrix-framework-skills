# Реакция на изменения CRM

Интерфейс, REST (`crm.item.*`, `crm.deal.*`), совместимые `CCrmDeal::Update()` и свой код проходят операции Factory в проверенной версии. Регистрируй обработчики при установке (`bitrix-events`): массивные события — `registerEventHandlerCompatible()`, события `Main\Event` — `registerEventHandler()`.

| Сущность | События модуля `crm` | Аргументы |
| --- | --- | --- |
| Лид, сделка, контакт, компания, предложение | `OnBeforeCrm{Entity}Add`, `OnBeforeCrm{Entity}Update`, `OnAfterCrm{Entity}Add`, `OnAfterCrm{Entity}Update` | `array &$fields` |
| Те же сущности | `OnBeforeCrm{Entity}Delete`, `OnAfterCrm{Entity}Delete` | `$id` |
| Сделка, лид, предложение | `OnAfterCrm{Entity}ProductRowsSave` (у сделки также `OnBefore…`) | `$id, array $rows` |
| Смарт-процессы, смарт-счета/документы | `onCrmDynamicItemAdd`, `onCrmDynamicItemUpdate`, `onCrmDynamicItemDelete` и варианты `…_{entityTypeId}` | `Main\Event`: `item`, `id` |
| Комментарий таймлайна | `OnAfterCrmTimelineCommentAdd`, `…Update`, `…Delete` | `Main\Event`: `ID` |
| Дело | `OnActivityAdd`, `OnActivityUpdate` / `OnActivityDelete` | `$id, array &$fields` / `$id` |

```php
final class DealHandler
{
    private static bool $busy = false;

    public static function onBeforeUpdate(array &$fields): bool
    {
        if ((float)($fields['OPPORTUNITY'] ?? 0) < 0) {
            $fields['RESULT_MESSAGE'] = 'Сумма не может быть отрицательной';
            return false; // отмена; сообщение попадёт в Result операции
        }
        return true;
    }

    public static function onAfterUpdate(array &$fields): void
    {
        if (self::$busy) {
            return; // собственное изменение ниже повторно входит в обработчик
        }
        self::$busy = true;
        try {
            // Получи сервис и выполни короткую работу либо поставь её в очередь.
        } finally {
            self::$busy = false;
        }
    }
}
```

## Ограничения

- `$fields` содержит весь элемент в legacy-ключах (`getCompatibleData()`), а не только изменения: `isset($fields['STAGE_ID'])` не доказывает смену стадии. Определяй изменения в действии операции (`getItemBeforeSave()`).
- Обработчики `OnBefore*` идут после обработки и проверки обязательных полей. Записанные в `$fields` значения сохраняются без повторной проверки.
- У смарт-процесса нет before-события; `onCrmDynamicItemUpdate` передаёт сохранённый Item без старых значений. Для валидации и сравнения подменяй фабрику.
- Изменение того же элемента из after-обработчика повторно запускает обработчики и роботов. Защищай его флагом от рекурсии.
- Роботы идут после событий; робот, меняющий поля, запускает новое изменение.
- HTTP, почту и другую долгую работу ставь в очередь (`bitrix-background-jobs`).

## Подмена фабрики для всех источников записи

Контейнер и фабрики — сервисы ServiceLocator. Глобальные `services` имеют приоритет: `registerByModuleSettings()` пропускает уже зарегистрированный код.

```php
// Глобальная .settings.php (bitrix-settings), секция 'services' => ['value' => [...]]
'crm.service.factory.deal' => ['className' => \Vendor\Crm\DealFactory::class],
'crm.service.container' => ['className' => \Vendor\Crm\Container::class], // смарт-процессы
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
    // Наследник Factory\Dynamic; внутри выбирай поведение по $this->getEntityTypeId().
    protected static $dynamicFactoriesClassName = DynamicFactory::class;
}
```

- Классы должны автозагружаться до подключения своего модуля: composer либо `Loader::registerNamespace()` в `init.php`. CRM создаёт их при первом обращении.
- Аналогично: `crm.service.factory.lead|contact|company|quote`. Смарт-счета/документы используют собственные фабрики.
- Вызывай `parent::`. Защищённые внутренние методы меняются при обновлениях CRM; перепроверяй наследников.

## Автоматизация

- Роботы и триггеры запускаются из операции, если `$factory->isAutomationEnabled()` и не вызван `disableAutomation()`.
- Свой робот — действие bizproc (`bitrix-bizproc`).
- Реестр триггеров задан в `Automation\Factory::getTriggerRegistry()`; модуль не может дополнить его классами. Из PHP запускай триггер «Вебхук» по коду (нужен `rest`, см. `WebHookTrigger::isEnabled()`):

```php
\Bitrix\Crm\Automation\Trigger\WebHookTrigger::execute(
    [['OWNER_TYPE_ID' => \CCrmOwnerType::Deal, 'OWNER_ID' => $dealId]],
    ['code' => 'paid'],
);
```
