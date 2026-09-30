---
name: bitrix-sessions
description: 'Bitrix sessions — Application::getSession(), getLocalSession(), kernel session, BX_SECURITY_SESSION_READONLY/VIRTUAL, session section (redis, memcache, database, separated mode). Use instead of $_SESSION, for AJAX lock issues or session storage.'
---

# Sessions

Baseline: main 23.0+ · Verified: main 26.800.0

Never touch `$_SESSION`: it bypasses lazy start, read-only and virtual modes.

```php
use Bitrix\Main\Application;

$session = Application::getInstance()->getSession(); // SessionInterface + ArrayAccess
$session->set('vendor.wizard', ['step' => 2]);
$state = $session->get('vendor.wizard');              // null if missing
if ($session->has('vendor.flash')) { $msg = $session->get('vendor.flash'); $session->remove('vendor.flash'); }
$session->regenerateId();                             // after login / privilege change
```

- Prefix keys with your module id; store scalars/arrays only.
- The session is written and closed before background jobs and agents run: writes from `addBackgroundJob()` are lost (`bitrix-background-jobs`). CLI, agents and Messenger have no session.

## Local session — "session cache"

```php
$local = Application::getInstance()->getLocalSession('vendor_cart'); // separate container per name
$local->set('productIds', [1, 2, 3]);
$ids = $local['productIds'] ?? [];
```

- Stored in the `cache` engine (Redis/Memcache/APC) keyed by session ID, saved at the end of the hit; with the files cache it falls back to `$_SESSION`.
- TTL: `.settings.php` `'session_local_storage' => ['value' => ['ttl' => 86400]]`.
- Use for carts, filters, wizard drafts: big or frequently changed data that should not bloat or lock the main session.

## Kernel session

`Application::getInstance()->getKernelSession()` holds kernel data (auth, `sessid`). In `default` mode it is the main session; in `separated` mode an encrypted cookie (`crypto_key`), no objects. Don't put application data there.

## Modes (define before `prolog_before.php`)

- `define('BX_SECURITY_SESSION_READONLY', true);` — handlers read without a lock and never write. For parallel read-only AJAX (suggest, counters); changes are discarded.
- `define('BX_SECURITY_SESSION_VIRTUAL', true);` — in-memory session, no cookie, nothing stored. For token-authenticated REST/webhooks.
- `define('BX_FORCE_DISABLE_SEPARATED_SESSION_MODE', true);` — force `default` mode for a script.

## `session` section (`bitrix-settings`)

```php
'session' => [
    'value' => [
        'mode' => 'separated',              // 'default' | 'separated'
        'lifetime' => 14400,                // kernel cookie lifetime, separated mode only
        'handlers' => [
            'kernel' => 'encrypted_cookies',  // the only allowed kernel handler
            'general' => [
                'type' => 'redis',            // file | database | redis | memcache | null
                'host' => '127.0.0.1',
                'port' => 6379,
                'keyPrefix' => 'site1',       // default 'BX'; unique per site sharing a server
            ],
        ],
        'regenerateIdAfterLogin' => true,
        'ignoreSessionStartErrors' => false, // true: hit continues if storage is down
    ],
    'readonly' => true,
],
```

- `separated`: kernel data in the cookie, general session starts lazily on first access — anonymous hits that never touch it don't hit storage.
- General session TTL for Redis/Memcache is `php.ini` `session.gc_maxlifetime`, not `lifetime`.
- Redis options: `host`, `port`, `password`, `servers` (list of `['host'=>…, 'port'=>…]` for a cluster), `serializer`, `persistent`, `failover`, `timeout`, `readTimeout`, `keyPrefix`, `exclusiveLock` (lock value = requested page, shown in the lock-timeout error). Memcache: `host`, `port`, `servers`, `connectionTimeout`, `keyPrefix`, `exclusiveLock`. File: `savePath`. Database: table `b_user_session` (no extra keys).
- No `session` section and `php.ini` `session.save_handler` ≠ `files` → PHP's own handler is used as is.
- Every writable session is locked for the whole request (Redis/Memcache wait up to ~60 s, then fatal): parallel AJAX of one user runs serially — use `BX_SECURITY_SESSION_READONLY` where nothing is written.

## Security

- Session cookie is always `HttpOnly`; `Secure`/`SameSite` come from `php.ini` `session.cookie_secure` / `session.cookie_samesite`.
- Regenerate the ID on login and privilege changes (`regenerateIdAfterLogin` or `regenerateId()`).
- Never store secrets or full user objects in the session; re-check rights on every request (`bitrix-security`).
