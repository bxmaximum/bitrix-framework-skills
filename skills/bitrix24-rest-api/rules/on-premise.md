# On-premise (box) specifics

Same API as the cloud; the differences are versions, network and limits.

## Where REST lives

- Module `rest`. `urlrewrite.php` maps `#^/rest/#` to `/bitrix/services/rest/index.php` (component `bitrix:rest.server`). Base path: option `rest` / `rest_server_path`, default `/rest`.
- Webhooks and local apps: *Developer resources* at `/devops/`.
- Webhooks require HTTPS: `Bitrix\Rest\APAuth\Auth` returns `INVALID_REQUEST` (`Https required.`) unless the constant `REST_APAUTH_ALLOW_HTTP` is defined. Define it only on a local dev stand. Behind a TLS-terminating proxy, make sure the request is seen as HTTPS.
- Without module `bitrix24` (every box), plan checks are off: `Bitrix\Rest\Engine\Access::isFeatureEnabled()` returns `true`, and `FEATURE_NOT_AVAILABLE_ON_CURRENT_PLAN` never appears.

## Versions lag the cloud

- Box updates ship after the cloud, and each box has its own module set and versions. Do not assume a method or event exists.
- At install time check with `methods` / `method.get` (lower-case name) and `events`. If something is missing, tell the admin the module and the minimum version (*Settings > Product Settings > Modules*).
- REST 3.0 needs rest 26.200+ and a V3-enabled version of the target module: check `rest.documentation.openapi` (`rules/rest-v3.md`).

## Authorization server and network

- The box validates app tokens against the authorization server: `oauth.bitrix24.tech` for RU/CIS license regions, `oauth.bitrix.info` otherwise (`Bitrix\Rest\Internal\Service\OAuth\AuthorizationServerUrlResolver`; option `rest` / `oauth_server` overrides, constant `BITRIX_OAUTH_URL` wins over both). Apps refresh tokens directly on that server (`rules/auth.md`).
- Box outbound: the authorization server (install, tokens, events), `*.bitrixsoft.com` (Developer resources, app catalog). If these are closed, apps do not install and events do not arrive.
- App server inbound: events and outbound webhooks come from Bitrix24 queue servers (`mp_actions-*`), not from the box. Build firewall rules from `https://dl.bitrix24.com/webhook/app-world.json` (`nodes`), refreshed every 5–10 min, not more than once a minute.
- App server outbound: the box URL (REST calls) and the authorization server.
- Outbound webhooks on a box need an active license; they do not work in demo mode.
- Fully isolated box (no external access): a custom auth validator (event `rest` / `OnRestCheckAuth`) plus custom event and auth providers in a `/local/modules` module (docs: "Application Authorization in Isolated Bitrix24 Box"). That drops the authorization server and makes security your job; last resort. Server-side REST extension: `bitrix-rest`.

## Limits

- No request-rate limit (`QUERY_LIMIT_EXCEEDED` is cloud infrastructure; it is not in the box kernel).
- Method-time limiter `Bitrix\Rest\Engine\Access\LoadLimiter` is off by default. Options in module `rest`: `load_limiter_active` (`Y` to enable), `load_limiter_second_limit` (default 420 s per 10 min), `load_limiter_connection_name` (a cache connection from `.settings.php` `connections`, for example Redis). When it trips: `OPERATION_TIME_LIMIT` (429).
- Execution time is capped only by PHP and web-server timeouts. Keep calls short anyway: the same code may run against the cloud.

## Checklist

- [ ] Required methods and events checked on the target box at install (`method.get`, `events`).
- [ ] Box can reach the authorization server; app server accepts the `app-world.json` nodes.
- [ ] Tokens refreshed on the authorization server from `server_endpoint`, not via the box.
- [ ] HTTPS end to end; `REST_APAUTH_ALLOW_HTTP` absent on production.
