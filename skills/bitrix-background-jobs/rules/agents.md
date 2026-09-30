# Agents (`CAgent`)

## Register

```php
\CAgent::AddAgent(
    name: \Vendor\Module\Infrastructure\Agent\SyncAgent::class . '::run();',
    module: 'vendor.module',
    period: 'N',          // see "Periodic or not"
    interval: 300,        // seconds
    next_exec: (new \Bitrix\Main\Type\DateTime())->add('+5 minutes')->toString(), // '' = now
    existError: false,    // default true: existing agent → false + ThrowException
);
```

- `name` is PHP code executed with `eval`; the agent is identified by it (plus `user_id`). Pass arguments inside the string: `"::run('s2', {$offset});"` — cast/escape them.
- `datecheck` argument is ignored by the kernel.
- `make:agent Sync -m vendor.module` (**Since main 25.900**) → `Vendor\Module\Infrastructure\Agent\SyncAgent` with `run()` returning `__METHOD__ . '();'`.
- Uninstall: `\CAgent::RemoveModuleAgents('vendor.module')` (also done by `ModuleManager::unRegisterModule()`).

## Agent method

```php
final class SyncAgent
{
    public static function run(int $offset = 0): string
    {
        $next = ServiceLocator::getInstance()->get(SyncService::class)->syncBatch($offset, 100);

        return $next === null
            ? self::class . '::run();'                    // done: back to the start next period
            : self::class . '::run(' . $next . ');';      // continue from $next
    }
}
```

- Return the next call string to keep the agent; return `''` or `null` to delete it.
- Exception → logged, agent stays locked and is retried after the lock expires (10 min).
- The kernel loads the agent's `module` before running; time zone is disabled (`CTimeZone::Disable()`); `$USER` is `null`; global `$pPERIOD` may be reassigned to change the next interval.

## Periodic or not

- `period: 'N'` — `NEXT_EXEC = now + interval` after each run.
- `period: 'Y'` — `NEXT_EXEC = previous planned time + interval` (catches up after downtime).
- With cron split (below) the flag also decides **where** it runs.

## Long agents run twice

A run selects due agents and sets `DATE_CHECK = now + 600 s`; it stops starting new ones after 570 s. An agent still running after 10 min becomes selectable again and runs **in parallel**. Nothing kills it.

- Split work into steps (return the call string with an offset, as above).
- Guard critical sections: `Application::getConnection()->lock('vendor_sync', 0)` / `unlock()` (`bitrix-database`).
- Hours of work → Messenger or a console command.

## Hits vs cron

On hits, agents run as a low-priority background job after the response (option `main:check_agents` = `Y`). They don't delay the page but hold the PHP worker; with low traffic they are late.

Partial cron — hits run `period: 'Y'` agents, cron runs `period: 'N'`:

```php
\Bitrix\Main\Config\Option::set('main', 'agents_use_crontab', 'Y');
// crontab: */5 * * * * php -f /var/www/site/bitrix/modules/main/tools/cron_events.php
```

All agents on cron:

1. `Option::set('main', 'agents_use_crontab', 'N')` and `Option::set('main', 'check_agents', 'N')` (hits stop running agents).
2. In `dbconn.php`: `if (!(defined('CHK_EVENT') && CHK_EVENT === true)) { define('BX_CRONTAB_SUPPORT', true); }`
3. Own `php_interface/cron_events.php`: set `$_SERVER['DOCUMENT_ROOT']`, `define('NO_KEEP_STATISTIC', true)`, `define('NOT_CHECK_PERMISSIONS', true)`, `define('CHK_EVENT', true)`, include `prolog_before.php`, `@set_time_limit(0)`, call `CAgent::CheckAgents()`, then `define('BX_CRONTAB_SUPPORT', true)`, `define('BX_CRONTAB', true)`, `CEvent::CheckEvents()` (mail queue, `bitrix-mail`).
4. Cron it every 1–5 min; remove the job for `tools/cron_events.php` to avoid double runs.

Check stored value (not default): `Option::get('main', 'agents_use_crontab', 'Z')` → `Z` means unset.

## Multisite

Agents are global: one list, no `SITE_ID`, no current site. Pass the site in the call string (`"::run('s2');"`), one agent per site if needed; never one cron job per site.
