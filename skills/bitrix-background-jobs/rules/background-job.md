# `Application::addBackgroundJob()`

`addBackgroundJob(callable $job, array $args = [], $priority = Application::JOB_PRIORITY_NORMAL)`

```php
use Bitrix\Main\Application;

Application::getInstance()->addBackgroundJob(
    [\Vendor\Module\Public\Service\WelcomeService::class, 'sendAsync'], // static method
    [$userId],
);
```

When it runs — `Application::end()` → response sent (`fastcgi_finish_request()`) → `terminate()`:

1. `OnAfterEpilog` handlers;
2. `session_write_close()` — session writes from a job are lost;
3. jobs by priority (`JOB_PRIORITY_NORMAL` = 100 first, `JOB_PRIORITY_LOW` = 50; kernel agents run at 50, mail events at 49), DB pool forced to master;
4. jobs added by jobs run in the same loop.

Rules:

- The user already has the response, but the PHP worker is busy until jobs finish — keep them short (seconds).
- An exception in one job is logged, the rest still run, then it is rethrown.
- No delivery guarantee: a crash or `exit` loses the job. Need retries, volume or a separate worker → Messenger (`rules/messenger.md`).
- Pass scalars in `$args`; don't capture request/session objects.
- Only on hits that end through `Application::end()`/`terminate()` (normal pages, controllers, `bitrix.php` CLI).
