---
name: bitrix-storage
description: 'Where to keep state in Bitrix — Config\Option module settings, Config\Feature feature flags, PersistentStorageInterface TTL storage, DeferredStorageDecorator vs cache and session. Use when storing settings, toggles or temporary server state.'
---

# Storage: Option, Feature, Persistent storage

Baseline: main 23.0+ · Verified: main 26.800.0

| Need | Use |
| --- | --- |
| Deploy-time config, secrets | `.settings_extra.php` / env (`bitrix-settings`) |
| Permanent admin-editable module setting | `Config\Option` + `default_option.php` |
| Feature toggle, gradual rollout per user | `Config\Feature` (**Since main 26.700**); older: `Option` `Y`/`N` |
| Server state that must live N seconds | `PersistentStorageInterface` (**Since main 25.1100**); older: own ORM table with an expiry column |
| Derived data, may vanish anytime | cache (`bitrix-caching`) |
| Per-visitor state | session (`bitrix-sessions`) |
| Large blobs | files (`bitrix-files`) |

Don't use `Option` for chatty runtime state (progress, tokens, checkpoints), cache for data that must survive, or Persistent storage for secrets.

## Option

```php
use Bitrix\Main\Config\Option;

$interval = (int)Option::get('vendor.module', 'sync_interval');   // falls back to default_option.php
Option::set('vendor.module', 'sync_interval', (string)$interval);
$stored = Option::getRealValue('vendor.module', 'sync_interval'); // null if never stored
$all = Option::getForModule('vendor.module');
Option::delete('vendor.module', ['name' => 'sync_interval']);   // no filter = all module options
```

- Values are strings; cast at the boundary. Defaults: `bitrix-modules` (`default_option.php`).
- Site override: pass `$siteId`; empty site = global.
- `set()` clears the options cache, runs `option_triggers.php`, fires `OnAfterSetOption_<name>` / `OnAfterSetOption` — not for per-hit writes.
- Distinguish "unset" from default: `getRealValue()` or a sentinel default (`Option::get('main', 'x', 'Z')`).

## Feature flags — **Since main 26.700**

A flag is a class in your module's `lib/` (the module is derived from the namespace: `Vendor\Shop\…` → `vendor.shop`):

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
        return [static fn (Context $context): bool => $context->userId !== null]; // all must pass
    }
}
```

```php
use Bitrix\Main\Config\Feature;
use Bitrix\Main\Config\Feature\Rules\UserIdRule;

if (Feature::isEnabled(NewCheckoutFlag::class)) { /* new flow */ }
Feature::allow(NewCheckoutFlag::class, UserIdRule::class, ['userIds' => [1, 42]]); // beta users
Feature::enable(NewCheckoutFlag::class);          // everyone; stored in b_feature_flag
Feature::resetToDefault(NewCheckoutFlag::class);  // rules stay: clearRules()
```

- Decision order: requirements → `deny` rules → `allow` rules → stored value → `enabledByDefault()`.
- Rules: `UserIdRule` (`userIds`), `ModuleMinVersionRule` (`module`, `minVersion`); own rule extends `AbstractRule` (`static createFromConfig(array $config)`, `check(Context $context): bool`).
- Context: current user by default; `new Context(?int $userId = null, array $params = [])`, IDs < 1 = anonymous.
- Pitfalls: an unknown/unloadable flag class returns **true** ("shipped and removed"); `enable()`/`disable()` silently do nothing if the namespace's module is not installed — never define flags in `php_interface`. Values and rules are cached 24 h; after direct table edits call `Feature::clearCache()`. `onEnable()`/`onDisable()` fire only on a real stored change.

## Persistent storage — **Since main 25.1100**

```php
$storage = \Bitrix\Main\DI\ServiceLocator::getInstance()
    ->get(\Bitrix\Main\Data\Storage\PersistentStorageInterface::class);

$storage->set('vendor.module.import.42', ['step' => 3], 3600); // TTL required
$state = $storage->get('vendor.module.import.42');              // null after expiry
$storage->delete('vendor.module.import.42');
```

- PSR-16 API; DB-backed (`ConnectionBasedPersistentStorage`). Key ≤ 255 chars (`module.feature.id`), JSON-serializable value.
- TTL: positive `int` seconds or `DateInterval`; `null`/`≤ 0` → `InvalidTtlException`. Keep ≤ 7 days. `clear()` is not supported.
- `new DeferredStorageDecorator($storage)` buffers writes until `save()` or destruction — many writes per hit, loss on crash acceptable.
