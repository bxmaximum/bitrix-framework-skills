# Events and placements

## Subscribing

| Way | How | Events |
| --- | --- | --- |
| App | `event.bind` from app code (OAuth only, webhook gets `WRONG_AUTH_TYPE`) | all, including offline |
| Outbound webhook | pick events and handler URL in *Developer resources* | online events from the UI list |

```json
{"event": "ONCRMDEALUPDATE", "handler": "https://app.example.com/b24/events", "auth": "…"}
```

- Bind in the install handler of every app version: handlers are removed when the app is updated or deleted.
- `auth_type` = user ID to issue tokens for that user; non-admins may only bind for themselves. Offline events need an admin.
- Events reach the handler only after the installation is complete.
- The handler URL must be public HTTPS: no `localhost`, private addresses or self-signed certificates.

## What arrives

POST `application/x-www-form-urlencoded`: `event`, `event_handler_id`, `data` (usually `data[FIELDS][ID]`), `ts`, `auth`.

- App events: `auth` has `access_token`, `expires_in`, `scope`, `domain`, `client_endpoint`, `server_endpoint`, `member_id`, `application_token`. `refresh_token` only in long-lived events such as `ONAPPINSTALL`, `onOfflineEvent`.
- Outbound webhook: `auth` has only `domain`, `client_endpoint`, `server_endpoint`, `member_id`, `application_token`.
- No tokens if an automation rule, workflow or agent made the change. Call back with the tokens you stored at install.
- The event says only that something changed: re-read the object (`crm.item.get` …) before acting.

## Handler rules

- Store `application_token` per `member_id` at `ONAPPINSTALL`, replace it at `ONAPPUPDATE`. For an outbound webhook take it from the webhook form.
- Compare with `hash_equals()`. On mismatch return 403 and stop. For `ONAPPUNINSTALL` this is the only check (no tokens arrive).
- Bitrix24 does not retry: if the handler fails or is slow, the event is lost, and the queue server slows its calls. Persist the payload, answer 200, process in a worker (`bitrix-background-jobs`).
- Mass changes produce one call per object. Make processing idempotent: re-read state and upsert; dedupe on `member_id` + `event` + object ID + `ts`.
- Do not write back data that re-fires the same event without a loop guard.

```php
$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$auth = $request->getPost('auth');
$memberId = is_array($auth) ? (string)($auth['member_id'] ?? '') : '';
$token = is_array($auth) ? (string)($auth['application_token'] ?? '') : '';
$stored = $this->tokens->findApplicationToken($memberId); // your storage

if ($stored === null || !hash_equals($stored, $token))
{
    // respond 403; log event name and member_id only
}
$this->queue->push($request->getPostList()->toArray()); // then respond 200
```

Expose the handler as a route or controller action without session and CSRF prefilters (`bitrix-routing`, `bitrix-controllers`); `application_token` is the authentication.

## Offline events (no inbound HTTP)

- `event.bind` with `event_type: offline`; Bitrix24 queues changes on its side. Admin + app context only.
- The queue keeps the latest state per object (one record per `MESSAGE_ID`), not every change.
- Poll `event.offline.get` (`limit` default 50). Default `clear: 1` deletes returned records at once, so a crash loses them. Prefer `clear: 0` → `process_id`, then `event.offline.clear` after processing or `event.offline.error` for failures. That mode needs feature `rest_offline_extended` (`feature.get`).
- `auth_connector` keeps changes made by your own app out of your queue (no sync echo).
- `onOfflineEvent` can notify you that the queue is not empty (`options.minTimeout`, default 1 s).

## Placements (widgets)

- `placement.bind {PLACEMENT, HANDLER, TITLE, LANG_ALL, OPTIONS}`, usually at install. App context only.
- Scopes: `placement` plus the tool scope for many placements (`crm` for CRM tabs, `task` for tasks). Check the placement page header.
- Rights: admin. **Since rest 26.600** a non-admin may also bind or unbind for a local app (needs the right to create local apps) or a personal app they own; Marketplace apps stay admin-only (`Bitrix\Rest\Api\Placement::checkPermission()`, `AppAccessChecker`). On older versions call it as an admin.
- The handler gets the frame POST (`AUTH_ID`, `REFRESH_ID`, `APPLICATION_TOKEN`, `member_id`, `PLACEMENT`) and `PLACEMENT_OPTIONS`, a JSON string with the context (for example the deal `ID`). Validate before use.
- The page must be embeddable: no `X-Frame-Options: DENY` or blocking CSP `frame-ancestors`.
- Widgets are hidden until installation is complete and are shown only to users with access to the app.
- Errors: `ERROR_PLACEMENT_NOT_FOUND` (wrong code or missing tool scope), `ERROR_WRONG_HANDLER_URL`, `ERROR_PLACEMENT_MAX_COUNT`.
