# Authentication and scopes

## Choose the integration type

| Need | Use | Auth |
| --- | --- | --- |
| Script or system sync with one portal, no UI | Inbound webhook | `https://{host}/rest/{userId}/{code}/{method}` |
| React to changes in one portal, no app | Outbound webhook | handler gets `auth[application_token]` |
| Page inside Bitrix24, no own server | Local static app (HTML/JS archive) | frame auth via BX24.js / b24jssdk; receives no events |
| Page, widgets, events, server logic, one portal | Local server app with UI | simplified OAuth: tokens POSTed to the page |
| Background sync and events, one portal | Local server app without UI | tokens POSTed to the install handler |
| Many portals, Marketplace | Marketplace app (partner area keys) | full OAuth 2.0 |

- A webhook runs as the user who created it, within the scopes chosen for it. It cannot call methods that need an application context (`event.bind`, `placement.*`, some telephony and chat-bot methods): they return `WRONG_AUTH_TYPE`.
- Webhooks and local apps are created in *Developer resources* (cloud: Applications > Developer resources; on-premise: `/devops/`). A local app gets `client_id` (`local.…`) and `client_secret`.
- The portal identity is `member_id`: it survives domain changes. Key stored tokens and `application_token` by `member_id`.

## Full OAuth 2.0 flow

1. Redirect the user to `https://{portal}/oauth/authorize/?client_id=…&state=…`. `redirect_uri` comes from the app settings, not from the request.
2. The callback gets `code` (valid 30 s), `state`, `domain`, `member_id`, `server_domain`.
3. Server side: `GET https://{server_domain}/oauth/token/?grant_type=authorization_code&client_id=…&client_secret=…&code=…`.
4. The response has `access_token` (1 h), `refresh_token` (180 days), `client_endpoint` (call base), `server_endpoint`, `member_id`, `scope`.

Call `{client_endpoint}{method}` with the token in `auth` (query string or JSON body).

## Refresh

- Refresh after `expired_token` (401), then retry the call once: `grant_type=refresh_token&client_id=…&client_secret=…&refresh_token=…` on the authorization server. Store the new pair in place of the old one.
- Do not refresh before every call or on a timer: the authorization server blocks such clients. An idle integration refreshes at least once per 180 days.
- Serialize refresh per `member_id` so parallel workers do not race on one refresh token.
- Call the authorization server directly, not `{portal}/oauth/token/`: on-premise may not proxy it. Take its host from `server_endpoint` / `server_domain` instead of hardcoding `oauth.bitrix.info`. On-premise with a RU/CIS license resolves to `oauth.bitrix24.tech` (**Since rest 26.200\***; older kernels use option `rest` / `oauth_server`, default `oauth.bitrix.info`).
- `client_secret` goes only to the authorization server, never to the portal or the browser.
- Authorization server errors: `invalid_grant` (refresh token expired, run the full flow again), `invalid_client` (wrong keys or app not installed), `PAYMENT_REQUIRED` (paid app not paid; no tokens until it is).

## Frame apps and installation

- An app page or placement handler gets a POST with `AUTH_ID`, `AUTH_EXPIRES`, `REFRESH_ID`, `APPLICATION_TOKEN`, `member_id`, `status`, `PLACEMENT`, `PLACEMENT_OPTIONS`. `DOMAIN`, `PROTOCOL`, `LANG`, `APP_SID` arrive in the query string.
- `status` in the POST is informational. Get a trusted status from `app.info` on the authorization server.
- App without UI: fill *Initial installation path*. Right after install Bitrix24 POSTs `auth[...]` (form-urlencoded) there: persist `refresh_token` and `application_token`.
- With "Application completes the installation itself" on, events and widgets stay inactive until the setup page calls `BX24.installFinish()` (or b24jssdk `installFinish()`).

## Scopes

- Take the scope from the method page ("Scope:" line). Widgets need `placement` plus the tool scope.
- `scope` returns the granted scopes; `{"full": true}` returns all scopes the portal offers.
- `method.get {"name": "…"}` returns `isExisting` / `isAvailable` for the current credentials. Pass the name in lower case.
- A missing scope shows up as `insufficient_scope` (401) or `ERROR_METHOD_NOT_FOUND` (404).
