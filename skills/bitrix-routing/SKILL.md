---
name: bitrix-routing
description: 'D7 routing — /local/routes, RoutingConfigurator, route params, closures, controller-rendered public pages, PublicPageController, urlrewrite.php migration. Use when adding or debugging public or API URLs.'
---

# Bitrix Routing

Baseline: main 23.0+ · Verified: main 26.800.0

| Task | Read |
| --- | --- |
| Web server rule, `routing` config, module route files | `rules/setup.md` |
| `web.php`, verbs, params, names, groups, closure handlers | `rules/routes-handlers.md` |
| Public page on a controller, `PublicPageController` | `rules/public-pages.md` |
| Match order, compile cache, site-guard, `urlrewrite.php` migration | `rules/matching-legacy.md` |

## Invariants

- User routes only in `/local/routes/*.php`, listed by basename in `routing.config`. Module route files are `require`d from there.
- Handlers are thin: `[Controller::class, 'action']` for anything with auth, CSRF or a stable contract. Protect routed controllers with filters (`bitrix-controllers`), never with `->middleware()`.
- Routed controllers run in `SCOPE_AJAX`: the default `Authentication` + `Csrf` apply to GET pages too. Drop them per action for public read-only pages.
- Explicit verbs (`get`/`post`/…) for state-changing routes, `->where()` on every param.
- A physical file at the same path wins over the route: the web server serves it before `routing_index.php`.
