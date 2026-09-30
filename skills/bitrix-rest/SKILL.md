---
name: bitrix-rest
description: 'Expose your module''s methods through the rest module — REST 3.0 RestController, Engine controllers with restIntegration, OnRestServiceBuildDescription, scopes. Use when adding REST methods, events or placements to a /local module.'
---

# REST server side (`rest`)

Baseline: main 23.0+ · Verified: rest 26.650.0, main 26.800.0

Publishing methods from your module. Calling REST (webhooks, OAuth, batch, limits, error handling on the client): `bitrix24-rest-api`.

| Path | Registration | Read |
| --- | --- | --- |
| REST 3.0 (`/rest/api/…`, DTO, JSON) | module `.settings.php` → `rest` section | `rules/v3.md` |
| Engine controller (same class as AJAX) | `.settings.php` → `controllers.restIntegration.enabled` | `rules/classic.md` |
| Classic method map, REST events, placements | event `rest:OnRestServiceBuildDescription` | `rules/classic.md` |

For new APIs: REST 3.0 (**Since rest 26.200\***), fallback Engine controller + `restIntegration`. Classic descriptions: for REST events, placements, or explicit method names.

## Invariants

- `Loader::includeModule('rest')`; code lives in `/local/modules/<vendor.module>/`, the module must be installed.
- REST calls have no CSRF: authentication is the app token/webhook. Check the user's rights inside every action.
- Scopes are the permission units granted to apps. Keep names stable and documented.
- Return errors in REST form (v3 exceptions, controller `addError`, `\Bitrix\Rest\RestException`), never HTML or `die()`.
- Don't extend `rest` classes or implement `Bitrix\Rest\Internal\*` contracts: signatures change in minor releases (26.650: `Engine\Access`, `Api\User`, `IncomingWebhookRepositoryInterface`). Compose instead.
- Landing blocks and placements via REST: `bitrix-landing`.
