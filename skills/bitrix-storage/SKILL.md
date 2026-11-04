---
name: bitrix-storage
description: 'Используй для хранения состояния Битрикса: Config\Option, флаги Config\Feature и временные данные PersistentStorage. Выбор между БД, кешем и сессией.'
---

# Хранение: Option, Feature и данные с TTL

Baseline: main 23.0+ · Verified: main 26.1000.0

Перед PHP-кодом прочитай `bitrix-framework`, если ещё не читал его в этой задаче.

| Задача | Использовать |
| --- | --- |
| Конфигурация развёртывания и секреты | `.settings_extra.php` / env (`bitrix-settings`) |
| Постоянная настройка модуля, редактируемая администратором | `Config\Option` + `default_option.php` |
| Флаг возможности, постепенное включение по пользователям | `Config\Feature` (**Since main 26.700**); раньше `Option` `Y`/`N` |
| Серверное состояние на N секунд | `PersistentStorageInterface` (**Since main 25.1100**); раньше своя ORM-таблица со сроком действия |
| Производные данные, которые можно потерять | Кеш (`bitrix-caching`) |
| Состояние посетителя | Сессия (`bitrix-sessions`) |
| Большие двоичные данные | Файлы (`bitrix-files`) |

Не используй `Option` для частых изменений прогресса, токенов и контрольных точек; кеш — для обязательного сохранения данных; Persistent storage — для секретов.

## Option

```php
use Bitrix\Main\Config\Option;

$interval = (int)Option::get('vendor.module', 'sync_interval');   // запасное значение из default_option.php
Option::set('vendor.module', 'sync_interval', (string)$interval);
$stored = Option::getRealValue('vendor.module', 'sync_interval'); // null, если никогда не сохранялось
$all = Option::getForModule('vendor.module');
Option::delete('vendor.module', ['name' => 'sync_interval']);   // без фильтра удаляет все настройки модуля
```

- Значения — строки: приводи типы на границе. Значения по умолчанию: `bitrix-modules` (`default_option.php`).
- Для значения по сайту передавай `$siteId`; пустой сайт означает глобальное значение.
- `set()` очищает кеш настроек, запускает `option_triggers.php`, отправляет `OnAfterSetOption_<name>` / `OnAfterSetOption`. Не подходит для записи на каждом хите.
- Отличай отсутствие записи от значения по умолчанию: `getRealValue()` или сигнальное значение (`Option::get('main', 'x', 'Z')`).

## Флаги возможностей (**Since main 26.700**)

Флаг — класс в `lib/` своего модуля (модуль определяется по пространству имён: `Vendor\Shop\…` → `vendor.shop`):

```php
namespace Vendor\Shop\Config\Feature;

use Bitrix\Main\Config\Feature\AbstractFlag;
use Bitrix\Main\Config\Feature\Context;

final class NewCheckoutFlag extends AbstractFlag
{
    public function enabledByDefault(): bool
    {
        return false;
    }

    public function getRequirements(): array
    {
        return [static fn (Context $context): bool => $context->userId !== null]; // должны пройти все требования
    }
}
```

```php
use Bitrix\Main\Config\Feature;
use Bitrix\Main\Config\Feature\Rules\UserIdRule;

if (Feature::isEnabled(NewCheckoutFlag::class)) { /* новый сценарий */ }
Feature::allow(NewCheckoutFlag::class, UserIdRule::class, ['userIds' => [1, 42]]); // тестовые пользователи
Feature::enable(NewCheckoutFlag::class);          // для всех; хранится в b_feature_flag
Feature::resetToDefault(NewCheckoutFlag::class);  // правила остаются: clearRules()
```

- Порядок решения: требования → правила `deny` → правила `allow` → сохранённое значение → `enabledByDefault()`.
- Правила: `UserIdRule` (`userIds`), `ModuleMinVersionRule` (`module`, `minVersion`); своё наследует `AbstractRule` (`static createFromConfig(array $config)`, `check(Context $context): bool`).
- Контекст: текущий пользователь по умолчанию; `new Context(?int $userId = null, array $params = [])`; ID < 1 означает гостя.
- Неизвестный/незагружаемый класс флага возвращает **true** («поставлен и удалён»); `enable()`/`disable()` молча пропускаются, если модуль пространства имён не установлен. Не определяй флаги в `php_interface`. Значения и правила кешируются 24 часа; после прямых изменений таблиц вызывай `Feature::clearCache()`. `onEnable()`/`onDisable()` срабатывают только при реальном изменении сохранённого значения.

## Постоянное хранилище с TTL (**Since main 25.1100**)

```php
$storage = \Bitrix\Main\DI\ServiceLocator::getInstance()
    ->get(\Bitrix\Main\Data\Storage\PersistentStorageInterface::class);

$storage->set('vendor.module.import.42', ['step' => 3], 3600); // TTL обязателен
$state = $storage->get('vendor.module.import.42');              // null после истечения
$storage->delete('vendor.module.import.42');
```

- API PSR-16 с БД (`ConnectionBasedPersistentStorage`). Ключ до 255 символов (`module.feature.id`), значение сериализуемо в JSON.
- TTL: положительное число секунд `int` или `DateInterval`; `null`/`≤ 0` вызывает `InvalidTtlException`. Ограничивай семью днями. `clear()` не поддерживается.
- `new DeferredStorageDecorator($storage)` откладывает записи до `save()` или уничтожения: для множества записей за хит, когда потеря при сбое допустима.
