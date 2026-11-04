# События и права

## Двойная отправка

Событие записи отправляется **дважды**, модуль `''` (сущность глобального пространства), имя строится из `NAME` блока:

1. Старое `<Name><Event>`, например `ProductColorOnBeforeAdd`. Сокращённые данные: update без `primary`/`oldFields`, delete только `id`.
2. Современное `\<Name>::<Event>`, например `\ProductColor::OnBeforeAdd`. Полные данные; сюда подписывает `Bitrix\Main\ORM\EventManager`.

Подписывайся на один формат: оба вызовут обработчик дважды, ошибки обоих попадут в `Result`. Переименование `NAME` молча оставляет обработчики без событий.

```php
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\EntityError;
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventManager;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\ORM\Fields\FieldError;

// нужен скомпилированный класс данных; возвращает ключ для removeEventHandler()
$key = EventManager::getInstance()->addEventHandler(
    $dataClass,
    DataManager::EVENT_ON_BEFORE_ADD,
    static function (Event $event): EventResult {
        $result = new EventResult();
        $code = trim((string)($event->getParameter('fields')['UF_CODE'] ?? ''));
        if ($code === '') {
            $result->addError(new FieldError($event->getEntity()->getField('UF_CODE'), 'Код обязателен'));
            return $result;
        }
        $result->modifyFields(['UF_CODE' => mb_strtolower($code)]);
        return $result;
    }
);
```

- Также `removeEventHandler($dataClass, $type, $key)`, постоянные `registerEventHandler($dataClass, $type, $toModuleId, $toClass, $toMethod)` / `unRegisterEventHandler(...)`. Все разрешают сущность (блок должен компилироваться); неизвестный тип вызывает `ArgumentException`.
- Без компиляции на каждом хите подписывайся по имени: `\Bitrix\Main\EventManager::getInstance()->addEventHandler('', '\ProductColor::OnAfterAdd', [Handler::class, 'onAfterAdd'])` в `init.php` либо `registerEventHandler('', '\ProductColor::OnAfterAdd', 'vendor.module', Handler::class, 'onAfterAdd')` при установке (`bitrix-events`).

## Параметры современного формата

| Событие | Параметры | Отмена |
| --- | --- | --- |
| `OnBeforeAdd` | `fields`, изменяемый `object` | `addError()`; `modifyFields()`, `unsetFields()` |
| `OnAdd` / `OnAfterAdd` | `fields`, копия `object`; после: также `id`, `primary` | Нет |
| `OnBeforeUpdate` | `id`, `primary`, `fields` (только переданные ключи), `object`, `oldFields` | Как `OnBeforeAdd` |
| `OnUpdate` / `OnAfterUpdate` | Те же | Нет |
| `OnBeforeDelete` / `OnDelete` / `OnAfterDelete` | `id`, `primary`, `oldFields` | Только OnBeforeDelete |

- `OnBefore*` предшествуют проверке UF. `OnAdd`/`OnUpdate`/`OnDelete` — перед SQL. После ошибки `OnBeforeDelete` остальные события удаления не вызываются.
- `oldFields` содержит прежние скалярные значения; множественные ненадёжны, прочитай до `update()`. Блокировки и версионирования строки нет.
- `FieldError` — ошибка поля, `EntityError` — записи. `unsetFields()` молча убирает поле; для уведомления добавь `addError`. Изменение одного поля несколькими обработчиками зависит от порядка.
- Вызов класса данных из обработчика повторно вызывает события: защити `static $running` + `try/finally` (только внутри процесса). Побочные действия after-событий не объединены с записью транзакцией.

## Права

Задачи: `hblock_denied` (D, нет операций), `hblock_read` (R → `hl_element_read`), `hblock_write` (W → `hl_element_write`, `hl_element_delete`). Права на блок, не запись. **`hblock_write` не включает `hl_element_read`**. Административный интерфейс и компоненты принимают любую операцию за чтение.

```php
use Bitrix\Highloadblock\HighloadBlockRightsTable;
use Bitrix\Main\TaskTable;

$ops = HighloadBlockRightsTable::getOperationsName([$hlId])[$hlId] ?? []; // скалярный аргумент при пустом результате вызывает warning
$canRead = $USER->IsAdmin() || array_intersect(['hl_element_read', 'hl_element_write'], $ops) !== [];
$canWrite = $USER->IsAdmin() || in_array('hl_element_write', $ops, true);

// права выдавай миграцией; не зашивай TASK_ID
$taskId = (int)TaskTable::getList([
    'select' => ['ID'], 'filter' => ['=MODULE_ID' => 'highloadblock', '=NAME' => 'hblock_read'],
])->fetch()['ID'];
HighloadBlockRightsTable::add(['HL_ID' => $hlId, 'TASK_ID' => $taskId, 'ACCESS_CODE' => 'G2']);
```

`getOperationsName()` читает глобальный `$USER`: без него в CLI/агенте фатальная ошибка. Явно определи модель доступа, не подставляй администратора по умолчанию.
